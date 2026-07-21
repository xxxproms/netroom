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

test('the labels page counts what can be labelled', function () {
    Device::factory()->count(2)->create(['site_id' => $this->site->id]);

    actingAs($this->viewer)
        ->get('/labels')
        ->assertInertia(fn ($page) => $page
            ->component('labels/Index')
            ->where('counts.devices', 2));
});

test('the print sheet renders a QR code for each device', function () {
    Device::factory()->create(['site_id' => $this->site->id, 'name' => 'SW-LABEL-1']);

    $response = actingAs($this->viewer)->get('/labels/print?type=devices');

    $response->assertOk();
    expect($response->getContent())
        ->toContain('SW-LABEL-1')
        ->toContain('<svg');
});

test('an unknown label type is refused', function () {
    actingAs($this->viewer)
        ->get('/labels/print?type=teapots')
        ->assertNotFound();
});

test('the print sheet only labels devices the user may reach', function () {
    $otherDomain = VlanDomain::factory()->create();
    $otherSite = Site::factory()->create(['vlan_domain_id' => $otherDomain->id]);
    Device::factory()->create(['site_id' => $otherSite->id, 'name' => 'SW-OFFLIMITS']);

    $outsider = User::factory()->create(['has_all_sites' => false]);
    $outsider->assignRole('viewer');
    $outsider->sites()->attach($this->site);

    $response = actingAs($outsider)->get('/labels/print?type=devices');

    $response->assertOk();
    expect($response->getContent())->not->toContain('SW-OFFLIMITS');
});
