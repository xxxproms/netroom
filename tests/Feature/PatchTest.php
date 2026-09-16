<?php

use App\Models\Cable;
use App\Models\Device;
use App\Models\DeviceModel;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Site;
use App\Models\User;
use Database\Seeders\PermissionSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);

    $this->engineer = User::factory()->create(['has_all_sites' => true]);
    $this->engineer->assignRole('engineer');

    $this->site = Site::factory()->create();
    $this->room = Room::factory()->create(['site_id' => $this->site->id]);
    $this->rack = Rack::factory()->create(['room_id' => $this->room->id, 'u_height' => 20]);
});

function rackDevice(Site $site, Rack $rack, string $name, string $kind, int $position): Device
{
    return Device::factory()->create([
        'site_id' => $site->id,
        'rack_id' => $rack->id,
        'position_u' => $position,
        'device_model_id' => DeviceModel::factory()->create(['kind' => $kind])->id,
        'name' => $name,
    ]);
}

test('patch data pairs two ports in the same rack as an internal cord', function () {
    $switch = rackDevice($this->site, $this->rack, 'SW', 'switch', 1);
    $switchPort = $switch->ports()->create(['name' => '1', 'number' => 1, 'media' => 'rj45', 'role' => 'network']);

    $panel = rackDevice($this->site, $this->rack, 'PP', 'patch_panel', 3);
    $front = $panel->ports()->create(['name' => 'F1', 'number' => 1, 'media' => 'rj45', 'role' => 'front']);

    Cable::factory()->between($switchPort, $front)->create(['site_id' => $this->site->id]);

    $response = $this->actingAs($this->engineer)
        ->getJson("/racks/{$this->rack->id}/patch")
        ->assertOk();

    // Both devices are returned, the cable shows as a link, and nothing leaves.
    expect($response->json('devices'))->toHaveCount(2)
        ->and($response->json('externals'))->toHaveCount(0);

    $sw = collect($response->json('devices'))->firstWhere('name', 'SW');
    expect($sw['ports'][0]['link']['far']['id'])->toBe($front->id)
        ->and($sw['ports'][0]['link']['far']['device']['name'])->toBe('PP');
});

test('a cable to another rack shows up as an uplink', function () {
    $switch = rackDevice($this->site, $this->rack, 'SW-A', 'switch', 1);
    $near = $switch->ports()->create(['name' => '24', 'number' => 24, 'media' => 'rj45', 'role' => 'network']);

    $otherRack = Rack::factory()->create(['room_id' => $this->room->id, 'u_height' => 20]);
    $core = rackDevice($this->site, $otherRack, 'SW-CORE', 'switch', 1);
    $corePort = $core->ports()->create(['name' => '1', 'number' => 1, 'media' => 'rj45', 'role' => 'network']);

    Cable::factory()->between($near, $corePort)->create(['site_id' => $this->site->id]);

    $response = $this->actingAs($this->engineer)
        ->getJson("/racks/{$this->rack->id}/patch")
        ->assertOk();

    expect($response->json('devices'))->toHaveCount(1)
        ->and($response->json('externals'))->toHaveCount(1);

    $uplink = $response->json('externals.0');
    expect($uplink['near_port_id'])->toBe($near->id)
        ->and($uplink['far']['label'])->toContain('SW-CORE')
        ->and($uplink['far']['port_id'])->toBe($corePort->id);
});

test('the patch view is refused to someone who cannot see the rack', function () {
    $outsider = User::factory()->create();
    $outsider->assignRole('engineer');

    $this->actingAs($outsider)
        ->getJson("/racks/{$this->rack->id}/patch")
        ->assertForbidden();
});

test('a cord can be dragged between two ports and coloured', function () {
    $switch = rackDevice($this->site, $this->rack, 'SW', 'switch', 1);
    $port = $switch->ports()->create(['name' => '1', 'number' => 1, 'media' => 'rj45', 'role' => 'network']);

    $panel = rackDevice($this->site, $this->rack, 'PP', 'patch_panel', 3);
    $front = $panel->ports()->create(['name' => 'F1', 'number' => 1, 'media' => 'rj45', 'role' => 'front']);

    // What the drag posts: two ports, inferred media, a rotated colour.
    $this->actingAs($this->engineer)
        ->from("/racks/{$this->rack->id}")
        ->post('/cables', [
            'a_type' => 'port', 'a_id' => $port->id,
            'b_type' => 'port', 'b_id' => $front->id,
            'media' => 'utp', 'strands' => null,
            'color' => '#0284c7', 'status' => 'connected',
        ])
        ->assertSessionHasNoErrors();

    $cable = Cable::first();
    expect($cable->color)->toBe('#0284c7')
        ->and($cable->media)->toBe('utp');
});

test('a cord appearance can be edited without resending its ends', function () {
    $switch = rackDevice($this->site, $this->rack, 'SW', 'switch', 1);
    $port = $switch->ports()->create(['name' => '1', 'number' => 1, 'media' => 'rj45', 'role' => 'network']);
    $panel = rackDevice($this->site, $this->rack, 'PP', 'patch_panel', 3);
    $front = $panel->ports()->create(['name' => 'F1', 'number' => 1, 'media' => 'rj45', 'role' => 'front']);

    $cable = Cable::factory()->between($port, $front)->create(['site_id' => $this->site->id]);

    $this->actingAs($this->engineer)
        ->from("/racks/{$this->rack->id}")
        ->patch("/cables/{$cable->id}/appearance", [
            'label' => 'A-14',
            'length_cm' => 150,
            'color' => '#dc2626',
            'status' => 'planned',
        ])
        ->assertSessionHasNoErrors();

    $cable->refresh();
    expect($cable->label)->toBe('A-14')
        ->and($cable->length_cm)->toBe(150)
        ->and($cable->color)->toBe('#dc2626')
        ->and($cable->status)->toBe('planned');
});

test('editing a cord appearance is refused to a viewer', function () {
    $viewer = User::factory()->create(['has_all_sites' => true]);
    $viewer->assignRole('viewer');

    $switch = rackDevice($this->site, $this->rack, 'SW', 'switch', 1);
    $port = $switch->ports()->create(['name' => '1', 'number' => 1, 'media' => 'rj45', 'role' => 'network']);
    $panel = rackDevice($this->site, $this->rack, 'PP', 'patch_panel', 3);
    $front = $panel->ports()->create(['name' => 'F1', 'number' => 1, 'media' => 'rj45', 'role' => 'front']);
    $cable = Cable::factory()->between($port, $front)->create(['site_id' => $this->site->id]);

    $this->actingAs($viewer)
        ->patch("/cables/{$cable->id}/appearance", ['status' => 'connected'])
        ->assertForbidden();
});
