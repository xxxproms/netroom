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

function mountDevice(array $overrides = []): array
{
    return [
        'device_model_id' => $overrides['device_model_id'] ?? DeviceModel::factory()->withPorts(24)->create()->id,
        'site_id' => $overrides['site_id'],
        'rack_id' => $overrides['rack_id'] ?? null,
        'position_u' => $overrides['position_u'] ?? null,
        'face' => 'front',
        'name' => $overrides['name'] ?? 'SW-01',
        'status' => 'active',
    ];
}

test('creating a device lays out its ports from the model', function () {
    $model = DeviceModel::factory()->create();
    $model->portTemplates()->createMany([
        ['start_number' => 1, 'count' => 24, 'media' => 'rj45', 'speed_mbps' => 1000, 'role' => 'network', 'sort' => 0],
        ['start_number' => 25, 'count' => 4, 'media' => 'sfp', 'speed_mbps' => 1000, 'role' => 'network', 'sort' => 1],
    ]);

    $this->actingAs($this->engineer)
        ->post('/devices', mountDevice([
            'device_model_id' => $model->id,
            'site_id' => $this->site->id,
        ]))
        ->assertRedirect();

    $device = Device::firstWhere('name', 'SW-01');

    expect($device->ports()->count())->toBe(28)
        ->and($device->ports()->where('media', 'sfp')->pluck('number')->all())->toBe([25, 26, 27, 28]);
});

test('a patch panel wires each front port to its rear port', function () {
    $panel = DeviceModel::factory()->patchPanel(24)->create();

    $this->actingAs($this->engineer)
        ->post('/devices', mountDevice([
            'device_model_id' => $panel->id,
            'site_id' => $this->site->id,
            'name' => 'PP-01',
        ]))
        ->assertRedirect();

    $device = Device::firstWhere('name', 'PP-01');
    $front = $device->ports()->where('role', 'front')->where('number', 12)->first();

    expect($device->ports()->count())->toBe(48)
        ->and($front->rearPort)->not->toBeNull()
        ->and($front->rearPort->role)->toBe('rear')
        ->and($front->rearPort->number)->toBe(12);
});

test('a device cannot be mounted where another one already is', function () {
    $twoUnit = DeviceModel::factory()->create(['u_height' => 2]);

    Device::factory()->create([
        'device_model_id' => $twoUnit->id,
        'site_id' => $this->site->id,
        'rack_id' => $this->rack->id,
        'position_u' => 4,
        'face' => 'front',
        'name' => 'Existing',
    ]);

    $this->actingAs($this->engineer)
        ->from("/racks/{$this->rack->id}")
        ->post('/devices', mountDevice([
            'device_model_id' => $twoUnit->id,
            'site_id' => $this->site->id,
            'rack_id' => $this->rack->id,
            // Overlaps unit 5, which "Existing" occupies as its second unit.
            'position_u' => 5,
            'name' => 'Clashing',
        ]))
        ->assertSessionHasErrors('position_u');

    expect(Device::where('name', 'Clashing')->exists())->toBeFalse();
});

test('the same units on the other face are free', function () {
    $model = DeviceModel::factory()->create();

    Device::factory()->create([
        'device_model_id' => $model->id,
        'site_id' => $this->site->id,
        'rack_id' => $this->rack->id,
        'position_u' => 4,
        'face' => 'front',
        'name' => 'Front device',
    ]);

    $this->actingAs($this->engineer)
        ->from("/racks/{$this->rack->id}")
        ->post('/devices', [
            ...mountDevice([
                'device_model_id' => $model->id,
                'site_id' => $this->site->id,
                'rack_id' => $this->rack->id,
                'position_u' => 4,
                'name' => 'Rear device',
            ]),
            'face' => 'rear',
        ])
        ->assertSessionHasNoErrors();
});

test('a device cannot hang off the top of its rack', function () {
    $tall = DeviceModel::factory()->create(['u_height' => 4]);

    $this->actingAs($this->engineer)
        ->from("/racks/{$this->rack->id}")
        ->post('/devices', mountDevice([
            'device_model_id' => $tall->id,
            'site_id' => $this->site->id,
            'rack_id' => $this->rack->id,
            // The rack is 10U, so a 4U device may start at 7 at the latest.
            'position_u' => 8,
        ]))
        ->assertSessionHasErrors('position_u');
});

test('management addresses are unique across the estate', function () {
    Device::factory()->create(['site_id' => $this->site->id, 'mgmt_ip' => '10.40.0.100']);

    $this->actingAs($this->engineer)
        ->from('/devices')
        ->post('/devices', [
            ...mountDevice(['site_id' => $this->site->id, 'name' => 'Duplicate IP']),
            'mgmt_ip' => '10.40.0.100',
        ])
        ->assertSessionHasErrors('mgmt_ip');
});

test('a device can be dragged to a free unit', function () {
    $device = Device::factory()->create([
        'site_id' => $this->site->id,
        'rack_id' => $this->rack->id,
        'position_u' => 1,
    ]);

    $this->actingAs($this->engineer)
        ->from("/racks/{$this->rack->id}")
        ->put("/racks/{$this->rack->id}/devices/{$device->id}/position", [
            'position_u' => 6,
            'face' => 'front',
        ])
        ->assertSessionHasNoErrors();

    expect($device->refresh()->position_u)->toBe(6);
});

test('dragging a device onto an occupied unit is refused', function () {
    $first = Device::factory()->create([
        'site_id' => $this->site->id,
        'rack_id' => $this->rack->id,
        'position_u' => 1,
    ]);

    Device::factory()->create([
        'site_id' => $this->site->id,
        'rack_id' => $this->rack->id,
        'position_u' => 6,
        'name' => 'Occupier',
    ]);

    $this->actingAs($this->engineer)
        ->from("/racks/{$this->rack->id}")
        ->put("/racks/{$this->rack->id}/devices/{$first->id}/position", [
            'position_u' => 6,
            'face' => 'front',
        ])
        ->assertSessionHasErrors('position_u');

    expect($first->refresh()->position_u)->toBe(1);
});

test('a device from another site is not visible', function () {
    $device = Device::factory()->create(['site_id' => $this->site->id]);

    $outsider = User::factory()->create();
    $outsider->assignRole('engineer');

    $this->actingAs($outsider)->get("/devices/{$device->id}")->assertForbidden();
});

test('a port description can be changed but its identity cannot', function () {
    $device = Device::factory()->create(['site_id' => $this->site->id]);
    $port = $device->ports()->create([
        'name' => '7', 'number' => 7, 'media' => 'rj45', 'role' => 'network',
    ]);

    $this->actingAs($this->engineer)
        ->from("/devices/{$device->id}")
        ->patch("/ports/{$port->id}", [
            'description' => 'Reception desk',
            'is_uplink' => false,
            'enabled' => true,
        ])
        ->assertSessionHasNoErrors();

    expect($port->refresh()->description)->toBe('Reception desk')
        ->and($port->name)->toBe('7');
});

test('a device can be given its own colour on the elevation', function () {
    $device = Device::factory()->create(['site_id' => $this->site->id]);

    $this->actingAs($this->engineer)
        ->from("/devices/{$device->id}")
        ->patch("/devices/{$device->id}", [
            'device_model_id' => $device->device_model_id,
            'site_id' => $device->site_id,
            'face' => 'front',
            'name' => $device->name,
            'status' => 'active',
            'color' => '#7c3aed',
        ])
        ->assertSessionHasNoErrors();

    expect($device->refresh()->color)->toBe('#7c3aed');
});

test('a colour that is not a hex value is refused', function () {
    $device = Device::factory()->create(['site_id' => $this->site->id]);

    $this->actingAs($this->engineer)
        ->from("/devices/{$device->id}")
        ->patch("/devices/{$device->id}", [
            'device_model_id' => $device->device_model_id,
            'site_id' => $device->site_id,
            'face' => 'front',
            'name' => $device->name,
            'status' => 'active',
            'color' => 'purple',
        ])
        ->assertSessionHasErrors('color');
});
