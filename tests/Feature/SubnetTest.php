<?php

use App\Models\Device;
use App\Models\Site;
use App\Models\Subnet;
use App\Models\User;
use App\Models\VlanDomain;
use App\Support\Cidr;
use App\Support\SubnetUsage;
use Database\Seeders\PermissionSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);

    $this->engineer = User::factory()->create(['has_all_sites' => true]);
    $this->engineer->assignRole('engineer');

    $this->domain = VlanDomain::factory()->create();
    $this->site = Site::factory()->create(['vlan_domain_id' => $this->domain->id]);
});

function subnet(VlanDomain $domain, string $cidr = '10.40.0.0/24', ?string $gateway = null): Subnet
{
    $range = Cidr::parse($cidr);

    return Subnet::create([
        'vlan_domain_id' => $domain->id,
        'cidr' => $range->label(),
        'network' => $range->network,
        'broadcast' => $range->broadcast(),
        'gateway' => $gateway,
    ]);
}

test('the cidr helper masks to the network and counts hosts', function () {
    $range = Cidr::parse('10.40.0.37/24');

    expect($range->label())->toBe('10.40.0.0/24')
        ->and($range->hostCount())->toBe(254)
        ->and($range->contains(Cidr::toLong('10.40.0.200')))->toBeTrue()
        ->and($range->contains(Cidr::toLong('10.41.0.1')))->toBeFalse();
});

test('a device management IP shows up in its subnet on its own', function () {
    Device::factory()->create([
        'site_id' => $this->site->id,
        'mgmt_ip' => '10.40.0.50',
        'name' => 'SW-1',
    ]);

    $usage = app(SubnetUsage::class)->summarise(subnet($this->domain));

    expect($usage['used'])->toBe(1)
        ->and($usage['occupants'][0]['address'])->toBe('10.40.0.50')
        ->and($usage['occupants'][0]['claims'][0]['source'])->toBe('device');
});

test('a hand reservation on a device address is flagged as a conflict', function () {
    $subnet = subnet($this->domain);

    Device::factory()->create([
        'site_id' => $this->site->id,
        'mgmt_ip' => '10.40.0.60',
        'name' => 'SW-2',
    ]);

    // Reserved by hand, pointing at no device — two independent claims.
    $subnet->addresses()->create([
        'address' => Cidr::toLong('10.40.0.60'),
        'address_text' => '10.40.0.60',
        'status' => 'reserved',
    ]);

    $usage = app(SubnetUsage::class)->summarise($subnet);

    expect($usage['conflicts'])->toBe(1)
        ->and($usage['occupants'][0]['conflict'])->toBeTrue();
});

test('a reservation naming the device that owns the address is not a conflict', function () {
    $subnet = subnet($this->domain);

    $device = Device::factory()->create([
        'site_id' => $this->site->id,
        'mgmt_ip' => '10.40.0.70',
    ]);

    $subnet->addresses()->create([
        'address' => Cidr::toLong('10.40.0.70'),
        'address_text' => '10.40.0.70',
        'device_id' => $device->id,
        'status' => 'assigned',
    ]);

    expect(app(SubnetUsage::class)->summarise($subnet)['conflicts'])->toBe(0);
});

test('the next free address skips everything already taken', function () {
    $subnet = subnet($this->domain);

    Device::factory()->create(['site_id' => $this->site->id, 'mgmt_ip' => '10.40.0.1']);
    Device::factory()->create(['site_id' => $this->site->id, 'mgmt_ip' => '10.40.0.2']);

    expect(app(SubnetUsage::class)->nextFree($subnet))->toBe('10.40.0.3');
});

test('an engineer creates a subnet from a CIDR', function () {
    $this->actingAs($this->engineer)
        ->post('/subnets', [
            'vlan_domain_id' => $this->domain->id,
            'cidr' => '192.168.10.0/24',
            'gateway' => '192.168.10.1',
            'name' => 'Office',
        ])
        ->assertRedirect();

    $subnet = Subnet::firstWhere('cidr', '192.168.10.0/24');

    expect($subnet)->not->toBeNull()
        ->and($subnet->network)->toBe(Cidr::toLong('192.168.10.0'));
});

test('a malformed CIDR is refused', function () {
    $this->actingAs($this->engineer)
        ->from('/subnets')
        ->post('/subnets', [
            'vlan_domain_id' => $this->domain->id,
            'cidr' => 'not-a-subnet',
        ])
        ->assertSessionHasErrors('cidr');
});

test('a gateway outside the subnet is refused', function () {
    $this->actingAs($this->engineer)
        ->from('/subnets')
        ->post('/subnets', [
            'vlan_domain_id' => $this->domain->id,
            'cidr' => '10.40.0.0/24',
            'gateway' => '10.99.0.1',
        ])
        ->assertSessionHasErrors('gateway');
});

test('a reserved address must fall inside its subnet', function () {
    $subnet = subnet($this->domain);

    $this->actingAs($this->engineer)
        ->from("/subnets/{$subnet->id}")
        ->post("/subnets/{$subnet->id}/addresses", [
            'address_text' => '10.99.0.5',
            'status' => 'reserved',
        ])
        ->assertSessionHasErrors('address_text');
});

test('two reservations cannot claim the same address', function () {
    $subnet = subnet($this->domain);

    $subnet->addresses()->create([
        'address' => Cidr::toLong('10.40.0.5'),
        'address_text' => '10.40.0.5',
        'status' => 'reserved',
    ]);

    $this->actingAs($this->engineer)
        ->from("/subnets/{$subnet->id}")
        ->post("/subnets/{$subnet->id}/addresses", [
            'address_text' => '10.40.0.5',
            'status' => 'reserved',
        ])
        ->assertSessionHasErrors('address_text');
});

test('a technician cannot manage IPAM', function () {
    $technician = User::factory()->create(['has_all_sites' => true]);
    $technician->assignRole('technician');

    $this->actingAs($technician)
        ->post('/subnets', [
            'vlan_domain_id' => $this->domain->id,
            'cidr' => '10.50.0.0/24',
        ])
        ->assertForbidden();
});

test('a subnet on a plan the user cannot reach is hidden', function () {
    $subnet = subnet($this->domain);

    $outsider = User::factory()->create(['has_all_sites' => false]);
    $outsider->assignRole('engineer');

    $this->actingAs($outsider)
        ->get("/subnets/{$subnet->id}")
        ->assertForbidden();
});
