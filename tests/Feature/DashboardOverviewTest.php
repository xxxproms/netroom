<?php

use App\Models\Device;
use App\Models\DeviceModel;
use App\Models\Site;
use App\Models\User;
use App\Models\VlanDomain;
use Database\Seeders\PermissionSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);

    $this->domain = VlanDomain::factory()->create();
    $this->site = Site::factory()->create(['vlan_domain_id' => $this->domain->id]);
});

test('the dashboard counts the estate the user can reach', function () {
    $engineer = User::factory()->create(['has_all_sites' => true]);
    $engineer->assignRole('engineer');

    Device::factory()->count(3)->create(['site_id' => $this->site->id]);

    actingAs($engineer)
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('stats.devices', 3)
            ->where('stats.sites', 1)
            ->has('attention')
            ->has('activity'));
});

test('a switch with no VLANs is flagged for attention', function () {
    $engineer = User::factory()->create(['has_all_sites' => true]);
    $engineer->assignRole('engineer');

    $model = DeviceModel::factory()->create(['kind' => 'switch']);
    Device::factory()->create([
        'site_id' => $this->site->id,
        'device_model_id' => $model->id,
    ]);

    actingAs($engineer)
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page->where('attention.switchesWithoutVlans', 1));
});

test('a user sees only the sites they may reach', function () {
    $otherDomain = VlanDomain::factory()->create();
    $otherSite = Site::factory()->create(['vlan_domain_id' => $otherDomain->id]);
    Device::factory()->count(2)->create(['site_id' => $otherSite->id]);

    Device::factory()->create(['site_id' => $this->site->id]);

    $outsider = User::factory()->create(['has_all_sites' => false]);
    $outsider->assignRole('engineer');
    $outsider->sites()->attach($this->site);

    actingAs($outsider)
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->where('stats.devices', 1)
            ->where('stats.sites', 1));
});
