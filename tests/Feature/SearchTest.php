<?php

use App\Models\Device;
use App\Models\Site;
use App\Models\User;
use App\Models\VlanDomain;
use Database\Seeders\PermissionSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);

    $this->domain = VlanDomain::factory()->create();
    $this->site = Site::factory()->create(['vlan_domain_id' => $this->domain->id]);

    $this->viewer = User::factory()->create(['has_all_sites' => true]);
    $this->viewer->assignRole('viewer');
});

test('a short query returns nothing to search on', function () {
    actingAs($this->viewer)
        ->getJson('/search?q=a')
        ->assertOk()
        ->assertExactJson(['groups' => []]);
});

test('a device is found by name and grouped', function () {
    Device::factory()->create(['site_id' => $this->site->id, 'name' => 'SW-CORE-01']);

    actingAs($this->viewer)
        ->getJson('/search?q=core')
        ->assertOk()
        ->assertJsonPath('groups.0.key', 'devices')
        ->assertJsonPath('groups.0.items.0.title', 'SW-CORE-01');
});

test('a device is found by management address', function () {
    Device::factory()->create([
        'site_id' => $this->site->id,
        'name' => 'SW-1',
        'mgmt_ip' => '10.40.0.9',
    ]);

    actingAs($this->viewer)
        ->getJson('/search?q=10.40.0.9')
        ->assertOk()
        ->assertJsonPath('groups.0.items.0.title', 'SW-1');
});

test('the search only reaches sites the user may see', function () {
    $otherDomain = VlanDomain::factory()->create();
    $otherSite = Site::factory()->create(['vlan_domain_id' => $otherDomain->id]);
    Device::factory()->create(['site_id' => $otherSite->id, 'name' => 'SW-HIDDEN']);

    $outsider = User::factory()->create(['has_all_sites' => false]);
    $outsider->assignRole('viewer');
    $outsider->sites()->attach($this->site);

    actingAs($outsider)
        ->getJson('/search?q=SW-HIDDEN')
        ->assertOk()
        ->assertExactJson(['groups' => []]);
});

test('LIKE wildcards in the query are treated literally', function () {
    Device::factory()->create(['site_id' => $this->site->id, 'name' => 'SW-1']);

    // A bare "%" would match everything if not escaped.
    actingAs($this->viewer)
        ->getJson('/search?q=%25')
        ->assertOk()
        ->assertExactJson(['groups' => []]);
});
