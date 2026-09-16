<?php

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
    $this->rack = Rack::factory()->create(['room_id' => $this->room->id, 'u_height' => 10]);
});

function quickServer(array $overrides = []): array
{
    return array_merge([
        'site_id' => $overrides['site_id'],
        'rack_id' => $overrides['rack_id'],
        'position_u' => 1,
        'face' => 'front',
        'name' => 'SRV-DB-01',
        'status' => 'active',
        'u_height' => 2,
        'port_count' => 4,
        'port_media' => 'rj45',
    ], $overrides);
}

test('a quick server is added to the rack with the ports asked for', function () {
    $this->actingAs($this->engineer)
        ->from("/racks/{$this->rack->id}")
        ->post('/servers', quickServer([
            'site_id' => $this->site->id,
            'rack_id' => $this->rack->id,
            'port_count' => 6,
        ]))
        ->assertRedirect("/racks/{$this->rack->id}");

    $server = Device::firstWhere('name', 'SRV-DB-01');

    expect($server)->not->toBeNull()
        ->and($server->deviceModel->kind)->toBe('server')
        ->and($server->deviceModel->u_height)->toBe(2)
        ->and($server->ports()->count())->toBe(6)
        ->and($server->ports()->where('role', 'network')->count())->toBe(6);
});

test('identical quick servers reuse one catalogue model', function () {
    $before = DeviceModel::count();

    $this->actingAs($this->engineer)
        ->post('/servers', quickServer([
            'site_id' => $this->site->id,
            'rack_id' => $this->rack->id,
            'position_u' => 1,
            'name' => 'SRV-A',
        ]))
        ->assertRedirect();

    $this->actingAs($this->engineer)
        ->post('/servers', quickServer([
            'site_id' => $this->site->id,
            'rack_id' => $this->rack->id,
            'position_u' => 4,
            'name' => 'SRV-B',
        ]))
        ->assertRedirect();

    // Two servers, but only one new "Server 2U · 4 x RJ45" model behind them.
    expect(DeviceModel::count())->toBe($before + 1)
        ->and(Device::whereIn('name', ['SRV-A', 'SRV-B'])->count())->toBe(2);
});

test('a quick server that does not fit the rack is refused', function () {
    $this->actingAs($this->engineer)
        ->from("/racks/{$this->rack->id}")
        ->post('/servers', quickServer([
            'site_id' => $this->site->id,
            'rack_id' => $this->rack->id,
            // A 4U server cannot start at unit 8 in a 10U rack.
            'position_u' => 8,
            'u_height' => 4,
        ]))
        ->assertSessionHasErrors('position_u');

    expect(Device::where('name', 'SRV-DB-01')->exists())->toBeFalse();
});

test('a quick server cannot overlap a device already mounted', function () {
    Device::factory()->create([
        'site_id' => $this->site->id,
        'rack_id' => $this->rack->id,
        'position_u' => 3,
        'face' => 'front',
        'name' => 'Existing',
    ]);

    $this->actingAs($this->engineer)
        ->from("/racks/{$this->rack->id}")
        ->post('/servers', quickServer([
            'site_id' => $this->site->id,
            'rack_id' => $this->rack->id,
            'position_u' => 3,
            'u_height' => 1,
        ]))
        ->assertSessionHasErrors('position_u');
});
