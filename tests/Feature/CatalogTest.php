<?php

use App\Models\DeviceModel;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Site;
use App\Models\User;
use Database\Seeders\DeviceModelSeeder;
use Database\Seeders\PermissionSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);

    $this->engineer = User::factory()->create(['has_all_sites' => true]);
    $this->engineer->assignRole('engineer');
});

test('a room belongs to a site and holds racks', function () {
    $site = Site::factory()->create();

    $this->actingAs($this->engineer)->post('/rooms', [
        'site_id' => $site->id,
        'name' => 'Server room 1',
        'kind' => 'server_room',
    ])->assertRedirect();

    $room = Room::firstWhere('name', 'Server room 1');

    $this->actingAs($this->engineer)->post('/racks', [
        'room_id' => $room->id,
        'name' => 'Rack 1',
        'u_height' => 42,
        'kind' => 'rack',
    ])->assertRedirect();

    expect($room->racks()->count())->toBe(1);
});

test('a room name is unique within its site but not across sites', function () {
    $first = Site::factory()->create();
    $second = Site::factory()->create();

    Room::factory()->create(['site_id' => $first->id, 'name' => 'Server room 1']);

    $this->actingAs($this->engineer)
        ->from('/rooms')
        ->post('/rooms', ['site_id' => $first->id, 'name' => 'Server room 1', 'kind' => 'server_room'])
        ->assertSessionHasErrors('name');

    $this->actingAs($this->engineer)
        ->from('/rooms')
        ->post('/rooms', ['site_id' => $second->id, 'name' => 'Server room 1', 'kind' => 'server_room'])
        ->assertSessionHasNoErrors();
});

test('a user cannot add a room to a site they cannot reach', function () {
    $site = Site::factory()->create();

    $engineer = User::factory()->create();
    $engineer->assignRole('engineer');

    $this->actingAs($engineer)
        ->post('/rooms', ['site_id' => $site->id, 'name' => 'Sneaky', 'kind' => 'server_room'])
        ->assertForbidden();

    expect(Room::count())->toBe(0);
});

test('the room list only covers sites the user may reach', function () {
    $allowed = Site::factory()->create();
    $hidden = Site::factory()->create();

    Room::factory()->create(['site_id' => $allowed->id]);
    Room::factory()->create(['site_id' => $hidden->id]);

    $engineer = User::factory()->create();
    $engineer->assignRole('engineer');
    $engineer->sites()->attach($allowed);

    $this->actingAs($engineer)
        ->get('/rooms')
        ->assertInertia(fn ($page) => $page->has('rooms', 1));
});

test('the site picker narrows the list to one site', function () {
    $first = Site::factory()->create();
    $second = Site::factory()->create();

    Room::factory()->count(2)->create(['site_id' => $first->id]);
    Room::factory()->create(['site_id' => $second->id]);

    $this->actingAs($this->engineer)
        ->withSession(['netroom.site' => $first->id])
        ->get('/rooms')
        ->assertInertia(fn ($page) => $page->has('rooms', 2));
});

test('the seeded catalogue matches the switches it was built from', function () {
    $this->seed(DeviceModelSeeder::class);

    $switch = DeviceModel::firstWhere('model', 'DGS-1210-28/ME');

    expect($switch->portCount())->toBe(28)
        ->and($switch->portTemplates()->where('media', 'sfp')->value('count'))->toBe(4)
        ->and(DeviceModel::firstWhere('model', 'DES-1210-10/ME')->portCount())->toBe(10);
});

test('a model expands its port templates into names', function () {
    $model = DeviceModel::factory()->create();

    $template = $model->portTemplates()->create([
        'name_prefix' => 'Gi',
        'start_number' => 25,
        'count' => 4,
        'media' => 'sfp',
        'role' => 'network',
    ]);

    expect(array_column($template->expand(), 'name'))
        ->toBe(['Gi25', 'Gi26', 'Gi27', 'Gi28']);
});

test('port templates are replaced when a model is saved', function () {
    $model = DeviceModel::factory()->withPorts(24)->create();

    $this->actingAs($this->engineer)->patch("/device-models/{$model->id}", [
        'vendor' => $model->vendor,
        'model' => $model->model,
        'kind' => 'switch',
        'u_height' => 1,
        'port_templates' => [
            ['name_prefix' => '', 'start_number' => 1, 'count' => 8, 'media' => 'rj45', 'speed_mbps' => 100, 'role' => 'network'],
            ['name_prefix' => '', 'start_number' => 9, 'count' => 2, 'media' => 'sfp', 'speed_mbps' => 1000, 'role' => 'network'],
        ],
    ])->assertRedirect();

    expect($model->fresh()->portCount())->toBe(10);
});

test('a technician cannot touch the catalogue', function () {
    $technician = User::factory()->create(['has_all_sites' => true]);
    $technician->assignRole('technician');

    $this->actingAs($technician)->post('/device-models', [
        'vendor' => 'D-Link',
        'model' => 'DGS-1210-28/ME',
        'kind' => 'switch',
        'u_height' => 1,
    ])->assertForbidden();
});

test('a rack cannot be taller than the form allows', function () {
    $room = Room::factory()->create();

    $this->actingAs($this->engineer)
        ->from('/rooms')
        ->post('/racks', ['room_id' => $room->id, 'name' => 'Too tall', 'u_height' => 99, 'kind' => 'rack'])
        ->assertSessionHasErrors('u_height');

    expect(Rack::count())->toBe(0);
});
