<?php

use App\Models\Device;
use App\Models\Site;
use App\Models\User;
use App\Models\Vlan;
use App\Models\VlanDomain;
use Database\Seeders\PermissionSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);

    $this->engineer = User::factory()->create(['has_all_sites' => true]);
    $this->engineer->assignRole('engineer');

    $this->domain = VlanDomain::factory()->create();
    $this->site = Site::factory()->create(['vlan_domain_id' => $this->domain->id]);
    $this->device = Device::factory()->create(['site_id' => $this->site->id]);

    $this->port = $this->device->ports()->create([
        'name' => '1', 'number' => 1, 'media' => 'rj45', 'role' => 'network',
    ]);

    $this->office = Vlan::factory()->create(['vlan_domain_id' => $this->domain->id, 'vid' => 20]);
    $this->voice = Vlan::factory()->create(['vlan_domain_id' => $this->domain->id, 'vid' => 10]);
    $this->mgmt = Vlan::factory()->create(['vlan_domain_id' => $this->domain->id, 'vid' => 3000]);
});

function applyMatrix(array $changes): array
{
    return ['changes' => $changes];
}

test('the matrix shows the site plan against the ports', function () {
    $this->port->vlans()->attach($this->office, ['mode' => 'untagged']);

    $this->actingAs($this->engineer)
        ->get("/devices/{$this->device->id}/vlans")
        ->assertInertia(fn ($page) => $page
            ->component('devices/Vlans')
            ->has('vlans', 3)
            ->has('ports', 1)
            ->where("membership.{$this->port->id}.{$this->office->id}", 'untagged'));
});

test('a port can be given tagged and untagged VLANs', function () {
    $this->actingAs($this->engineer)
        ->put("/devices/{$this->device->id}/vlans", applyMatrix([
            ['port_id' => $this->port->id, 'vlan_id' => $this->office->id, 'mode' => 'untagged'],
            ['port_id' => $this->port->id, 'vlan_id' => $this->mgmt->id, 'mode' => 'tagged'],
        ]))
        ->assertSessionHasNoErrors();

    expect($this->port->vlans()->count())->toBe(2)
        ->and($this->port->vlans()->where('vlans.id', $this->office->id)->first()->pivot->mode)
        ->toBe('untagged');
});

test('a second untagged VLAN replaces the first, the way a PVID does', function () {
    $this->port->vlans()->attach($this->office, ['mode' => 'untagged']);

    $this->actingAs($this->engineer)
        ->put("/devices/{$this->device->id}/vlans", applyMatrix([
            ['port_id' => $this->port->id, 'vlan_id' => $this->voice->id, 'mode' => 'untagged'],
        ]))
        ->assertSessionHasNoErrors();

    expect($this->port->vlans()->pluck('vlans.id')->all())->toBe([$this->voice->id]);
});

test('a tagged VLAN does not disturb the untagged one', function () {
    $this->port->vlans()->attach($this->office, ['mode' => 'untagged']);

    $this->actingAs($this->engineer)
        ->put("/devices/{$this->device->id}/vlans", applyMatrix([
            ['port_id' => $this->port->id, 'vlan_id' => $this->voice->id, 'mode' => 'tagged'],
        ]));

    expect($this->port->vlans()->count())->toBe(2);
});

test('clearing a cell removes the membership', function () {
    $this->port->vlans()->attach($this->office, ['mode' => 'tagged']);

    $this->actingAs($this->engineer)
        ->put("/devices/{$this->device->id}/vlans", applyMatrix([
            ['port_id' => $this->port->id, 'vlan_id' => $this->office->id, 'mode' => null],
        ]));

    expect($this->port->vlans()->count())->toBe(0);
});

test('a VLAN from another domain cannot be put on the port', function () {
    $foreign = Vlan::factory()->create(['vid' => 99]);

    $this->actingAs($this->engineer)
        ->from("/devices/{$this->device->id}/vlans")
        ->put("/devices/{$this->device->id}/vlans", applyMatrix([
            ['port_id' => $this->port->id, 'vlan_id' => $foreign->id, 'mode' => 'tagged'],
        ]))
        ->assertSessionHasErrors('changes.0.vlan_id');

    expect($this->port->vlans()->count())->toBe(0);
});

test('a port of another device cannot be edited through this matrix', function () {
    $other = Device::factory()->create(['site_id' => $this->site->id, 'name' => 'Other']);
    $foreignPort = $other->ports()->create([
        'name' => '1', 'number' => 1, 'media' => 'rj45', 'role' => 'network',
    ]);

    $this->actingAs($this->engineer)
        ->from("/devices/{$this->device->id}/vlans")
        ->put("/devices/{$this->device->id}/vlans", applyMatrix([
            ['port_id' => $foreignPort->id, 'vlan_id' => $this->office->id, 'mode' => 'tagged'],
        ]))
        ->assertSessionHasErrors('changes.0.port_id');
});

test('a technician may look at the matrix but not change it', function () {
    $technician = User::factory()->create(['has_all_sites' => true]);
    $technician->assignRole('technician');

    $this->actingAs($technician)
        ->get("/devices/{$this->device->id}/vlans")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('can.update', false));

    $this->actingAs($technician)
        ->put("/devices/{$this->device->id}/vlans", applyMatrix([
            ['port_id' => $this->port->id, 'vlan_id' => $this->office->id, 'mode' => 'tagged'],
        ]))
        ->assertForbidden();
});

test('the matrix of another site is out of reach', function () {
    $outsider = User::factory()->create();
    $outsider->assignRole('engineer');

    $this->actingAs($outsider)
        ->get("/devices/{$this->device->id}/vlans")
        ->assertForbidden();
});
