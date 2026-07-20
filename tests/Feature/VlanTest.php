<?php

use App\Models\Site;
use App\Models\User;
use App\Models\Vlan;
use App\Models\VlanDomain;
use Database\Seeders\PermissionSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);

    $this->engineer = User::factory()->create(['has_all_sites' => true]);
    $this->engineer->assignRole('engineer');
});

test('the same VID may exist in two domains', function () {
    $first = VlanDomain::factory()->create();
    $second = VlanDomain::factory()->create();

    Vlan::factory()->create(['vlan_domain_id' => $first->id, 'vid' => 100]);

    $this->actingAs($this->engineer)
        ->from('/vlans')
        ->post('/vlans', [
            'vlan_domain_id' => $second->id,
            'vid' => 100,
            'name' => 'office',
        ])
        ->assertSessionHasNoErrors();

    expect(Vlan::where('vid', 100)->count())->toBe(2);
});

test('a VID cannot be repeated inside one domain', function () {
    $domain = VlanDomain::factory()->create();
    Vlan::factory()->create(['vlan_domain_id' => $domain->id, 'vid' => 100]);

    $this->actingAs($this->engineer)
        ->from('/vlans')
        ->post('/vlans', [
            'vlan_domain_id' => $domain->id,
            'vid' => 100,
            'name' => 'duplicate',
        ])
        ->assertSessionHasErrors('vid');
});

test('a VLAN plan can be copied to another domain', function () {
    $source = VlanDomain::factory()->create();
    $target = VlanDomain::factory()->create();

    Vlan::factory()->create(['vlan_domain_id' => $source->id, 'vid' => 10, 'name' => 'jp']);
    Vlan::factory()->create(['vlan_domain_id' => $source->id, 'vid' => 20, 'name' => 'JPAS']);
    // Already present in the target: it must be left as it is.
    Vlan::factory()->create(['vlan_domain_id' => $target->id, 'vid' => 20, 'name' => 'kept']);

    $this->actingAs($this->engineer)
        ->from('/vlans')
        ->post('/vlans/copy', [
            'from_domain_id' => $source->id,
            'to_domain_id' => $target->id,
        ])
        ->assertSessionHasNoErrors();

    $target = $target->fresh();

    expect($target->vlans()->count())->toBe(2)
        ->and($target->vlans()->where('vid', 20)->value('name'))->toBe('kept')
        ->and($target->vlans()->where('vid', 10)->value('name'))->toBe('jp');
});

test('the VLAN list shows the plan of the site being viewed', function () {
    $domain = VlanDomain::factory()->create();
    $site = Site::factory()->create(['vlan_domain_id' => $domain->id]);
    Vlan::factory()->count(2)->create(['vlan_domain_id' => $domain->id]);

    $other = VlanDomain::factory()->create();
    Site::factory()->create(['vlan_domain_id' => $other->id]);
    Vlan::factory()->create(['vlan_domain_id' => $other->id]);

    $this->actingAs($this->engineer)
        ->withSession(['netroom.site' => $site->id])
        ->get('/vlans')
        ->assertInertia(fn ($page) => $page
            ->where('selectedDomainId', $domain->id)
            ->has('vlans', 2));
});

test('a technician cannot change the VLAN plan', function () {
    $domain = VlanDomain::factory()->create();

    $technician = User::factory()->create(['has_all_sites' => true]);
    $technician->assignRole('technician');

    $this->actingAs($technician)->post('/vlans', [
        'vlan_domain_id' => $domain->id,
        'vid' => 10,
        'name' => 'nope',
    ])->assertForbidden();
});
