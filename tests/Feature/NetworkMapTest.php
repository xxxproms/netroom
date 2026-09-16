<?php

use App\Models\Cable;
use App\Models\Device;
use App\Models\MapAnnotation;
use App\Models\Rack;
use App\Models\Site;
use App\Models\Tunnel;
use App\Models\User;
use App\Models\Vlan;
use Database\Seeders\PermissionSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);

    $this->engineer = User::factory()->create(['has_all_sites' => true]);
    $this->engineer->assignRole('engineer');

    $this->north = Site::factory()->create(['code' => 'N']);
    $this->south = Site::factory()->create(['code' => 'S']);
});

test('the global map shows every accessible site and tunnel', function () {
    Tunnel::factory()->create([
        'site_a_id' => $this->north->id,
        'site_b_id' => $this->south->id,
        'type' => 'kerio_vpn',
    ]);

    $this->actingAs($this->engineer)
        ->get('/map')
        ->assertInertia(fn ($page) => $page
            ->component('map/Index')
            ->has('sites', 2)
            ->has('tunnels', 1)
            ->where('tunnels.0.type', 'kerio_vpn'));
});

test('an engineer creates a tunnel between two sites', function () {
    $this->actingAs($this->engineer)
        ->from('/map')
        ->post('/tunnels', [
            'site_a_id' => $this->north->id,
            'site_b_id' => $this->south->id,
            'type' => 'ipsec',
            'status' => 'up',
        ])
        ->assertSessionHasNoErrors();

    expect(Tunnel::where('type', 'ipsec')->exists())->toBeTrue();
});

test('a tunnel cannot join a site to itself', function () {
    $this->actingAs($this->engineer)
        ->from('/map')
        ->post('/tunnels', [
            'site_a_id' => $this->north->id,
            'site_b_id' => $this->north->id,
            'type' => 'kerio_vpn',
            'status' => 'up',
        ])
        ->assertSessionHasErrors('site_b_id');
});

test('a terminator must stand at the site it terminates', function () {
    // A device at the south site cannot terminate the north end.
    $southDevice = Device::factory()->create(['site_id' => $this->south->id]);

    $this->actingAs($this->engineer)
        ->from('/map')
        ->post('/tunnels', [
            'site_a_id' => $this->north->id,
            'site_b_id' => $this->south->id,
            'device_a_id' => $southDevice->id,
            'type' => 'kerio_vpn',
            'status' => 'up',
        ])
        ->assertSessionHasErrors('device_a_id');
});

test('dragging a site saves its position', function () {
    $this->actingAs($this->engineer)
        ->from('/map')
        ->patch("/map/sites/{$this->north->id}/position", ['map_x' => 320, 'map_y' => 210])
        ->assertSessionHasNoErrors();

    expect($this->north->refresh()->map_x)->toBe(320)
        ->and($this->north->map_y)->toBe(210);
});

test('the site map reports port counts and management addresses', function () {
    $device = Device::factory()->create([
        'site_id' => $this->north->id,
        'mgmt_ip' => '10.0.0.1',
    ]);
    $device->ports()->create(['name' => '1', 'number' => 1, 'media' => 'rj45', 'role' => 'network']);
    $device->ports()->create(['name' => '2', 'number' => 2, 'media' => 'rj45', 'role' => 'network']);

    $this->actingAs($this->engineer)
        ->get("/map/sites/{$this->north->id}")
        ->assertInertia(fn ($page) => $page
            ->where('devices.0.ports_count', 2)
            ->where('devices.0.mgmt_ip', '10.0.0.1')
            ->has('devices.0.status'));
});

test('a site can be renamed and recoloured from the map', function () {
    $this->actingAs($this->engineer)
        ->from('/map')
        ->patch("/map/sites/{$this->north->id}/style", ['name' => 'Renamed', 'color' => '#123abc'])
        ->assertSessionHasNoErrors();

    expect($this->north->refresh()->name)->toBe('Renamed')
        ->and($this->north->color)->toBe('#123abc');
});

test('a device can be renamed and recoloured from the map', function () {
    $device = Device::factory()->create(['site_id' => $this->north->id, 'name' => 'Old']);

    $this->actingAs($this->engineer)
        ->from("/map/sites/{$this->north->id}")
        ->patch("/map/devices/{$device->id}/style", ['name' => 'New', 'color' => null])
        ->assertSessionHasNoErrors();

    expect($device->refresh()->name)->toBe('New')
        ->and($device->color)->toBeNull();
});

test('a map rename keeps device names unique within the site', function () {
    Device::factory()->create(['site_id' => $this->north->id, 'name' => 'Taken']);
    $device = Device::factory()->create(['site_id' => $this->north->id, 'name' => 'Mine']);

    $this->actingAs($this->engineer)
        ->from("/map/sites/{$this->north->id}")
        ->patch("/map/devices/{$device->id}/style", ['name' => 'Taken'])
        ->assertSessionHasErrors('name');
});

test('auto layout saves every node position in one request', function () {
    $this->actingAs($this->engineer)
        ->from('/map')
        ->patch('/map/positions', ['nodes' => [
            ['kind' => 'site', 'id' => $this->north->id, 'map_x' => 40, 'map_y' => 60],
            ['kind' => 'site', 'id' => $this->south->id, 'map_x' => 240, 'map_y' => 60],
        ]])
        ->assertSessionHasNoErrors();

    expect($this->north->refresh()->map_x)->toBe(40)
        ->and($this->south->refresh()->map_x)->toBe(240);
});

test('a batch of positions may mix nothing but valid coordinates', function () {
    $this->actingAs($this->engineer)
        ->from('/map')
        ->patch('/map/positions', ['nodes' => [
            ['kind' => 'site', 'id' => $this->north->id, 'map_x' => -5, 'map_y' => 60],
        ]])
        ->assertSessionHasErrors('nodes.0.map_x');
});

test('a batch move authorises every node it touches', function () {
    $restricted = User::factory()->create(['has_all_sites' => false]);
    $restricted->assignRole('engineer');
    $restricted->sites()->attach($this->north);

    $this->actingAs($restricted)
        ->patch('/map/positions', ['nodes' => [
            ['kind' => 'site', 'id' => $this->south->id, 'map_x' => 10, 'map_y' => 10],
        ]])
        ->assertForbidden();

    expect($this->south->refresh()->map_x)->not->toBe(10);
});

test('a viewer cannot restyle a node', function () {
    $viewer = User::factory()->create(['has_all_sites' => true]);
    $viewer->assignRole('viewer');

    $this->actingAs($viewer)
        ->patch("/map/sites/{$this->north->id}/style", ['name' => 'Nope'])
        ->assertForbidden();
});

test('the site map lists only cables between two of its own devices', function () {
    $a = Device::factory()->create(['site_id' => $this->north->id, 'name' => 'A']);
    $b = Device::factory()->create(['site_id' => $this->north->id, 'name' => 'B']);

    $portA = $a->ports()->create(['name' => '1', 'number' => 1, 'media' => 'rj45', 'role' => 'network']);
    $portB = $b->ports()->create(['name' => '1', 'number' => 1, 'media' => 'rj45', 'role' => 'network']);

    Cable::factory()->between($portA, $portB)->create(['site_id' => $this->north->id]);

    $this->actingAs($this->engineer)
        ->get("/map/sites/{$this->north->id}")
        ->assertInertia(fn ($page) => $page
            ->component('map/Site')
            ->has('devices', 2)
            ->has('links', 1)
            ->where('links.0.a', $a->id)
            ->where('links.0.b', $b->id));
});

test('the site map tells each device its rack and the VLANs it carries', function () {
    $rack = Rack::factory()->create();
    $device = Device::factory()->create([
        'site_id' => $this->north->id,
        'rack_id' => $rack->id,
    ]);

    $port = $device->ports()->create([
        'name' => '1', 'number' => 1, 'media' => 'rj45', 'role' => 'network',
    ]);
    $office = Vlan::factory()->create(['vid' => 20]);
    $voice = Vlan::factory()->create(['vid' => 10]);
    $port->vlans()->attach($office, ['mode' => 'untagged']);
    $port->vlans()->attach($voice, ['mode' => 'tagged']);

    $this->actingAs($this->engineer)
        ->get("/map/sites/{$this->north->id}")
        ->assertInertia(fn ($page) => $page
            ->where('devices.0.rack.id', $rack->id)
            ->where('devices.0.rack.name', $rack->name)
            // Sorted by VID, so the panel and the filter read the same way round.
            ->where('devices.0.vlans', [10, 20]));
});

test('a device outside a rack carries no rack and no VLANs', function () {
    Device::factory()->create(['site_id' => $this->north->id, 'rack_id' => null]);

    $this->actingAs($this->engineer)
        ->get("/map/sites/{$this->north->id}")
        ->assertInertia(fn ($page) => $page
            ->where('devices.0.rack', null)
            ->where('devices.0.vlans', []));
});

test('the global map names the VLAN domain each site belongs to', function () {
    // The map orders sites by name, and the factory names them at random, so the
    // site is picked out by its code rather than by where it landed in the list.
    $response = $this->actingAs($this->engineer)->get('/map');

    $sites = collect($response->viewData('page')['props']['sites']);

    expect($sites->firstWhere('code', 'N')['vlan_domain'])
        ->toBe($this->north->vlanDomain->name);
});

test('the global map carries its own drawings and no site ones', function () {
    MapAnnotation::factory()->create(['text' => 'Головной офис']);
    MapAnnotation::factory()->create(['site_id' => $this->north->id, 'text' => 'Серверная']);

    $this->actingAs($this->engineer)
        ->get('/map')
        ->assertInertia(fn ($page) => $page
            ->has('annotations', 1)
            ->where('annotations.0.text', 'Головной офис')
            ->where('annotations.0.type', 'zone'));
});

test('a site map carries only its own drawings', function () {
    MapAnnotation::factory()->create(['text' => 'Global']);
    MapAnnotation::factory()->note()->create([
        'site_id' => $this->north->id,
        'text' => 'Патч-панель ждёт замены',
    ]);

    $this->actingAs($this->engineer)
        ->get("/map/sites/{$this->north->id}")
        ->assertInertia(fn ($page) => $page
            ->has('annotations', 1)
            ->where('annotations.0.type', 'note')
            ->where('annotations.0.text', 'Патч-панель ждёт замены'));
});

test('an engineer draws a zone on a site map', function () {
    $this->actingAs($this->engineer)
        ->from("/map/sites/{$this->north->id}")
        ->post('/map/annotations', [
            'site_id' => $this->north->id,
            'type' => 'zone',
            'text' => 'Серверная 1',
            'map_x' => 100,
            'map_y' => 80,
            'width' => 320,
            'height' => 220,
            'color' => '#0891b2',
        ])
        ->assertSessionHasNoErrors();

    $zone = MapAnnotation::firstOrFail();

    expect($zone->text)->toBe('Серверная 1')
        ->and($zone->site_id)->toBe($this->north->id)
        ->and($zone->color)->toBe('#0891b2');
});

test('a drawing is retitled without losing where it sits', function () {
    $zone = MapAnnotation::factory()->create(['map_x' => 40, 'map_y' => 60]);

    $this->actingAs($this->engineer)
        ->from('/map')
        ->patch("/map/annotations/{$zone->id}", ['text' => 'Этаж 2'])
        ->assertSessionHasNoErrors();

    expect($zone->refresh()->text)->toBe('Этаж 2')
        ->and($zone->map_x)->toBe(40)
        ->and($zone->map_y)->toBe(60);
});

test('resizing a drawing saves its whole box', function () {
    $zone = MapAnnotation::factory()->create();

    $this->actingAs($this->engineer)
        ->from('/map')
        ->patch("/map/annotations/{$zone->id}", [
            'map_x' => 20,
            'map_y' => 30,
            'width' => 480,
            'height' => 300,
        ])
        ->assertSessionHasNoErrors();

    expect($zone->refresh()->width)->toBe(480)
        ->and($zone->height)->toBe(300)
        ->and($zone->text)->not->toBeNull();
});

test('a drawing cannot be shrunk past what stays readable', function () {
    $zone = MapAnnotation::factory()->create();

    $this->actingAs($this->engineer)
        ->from('/map')
        ->patch("/map/annotations/{$zone->id}", ['width' => 10, 'height' => 10])
        ->assertSessionHasErrors(['width', 'height']);
});

test('a batch move carries the drawings alongside the kit', function () {
    $zone = MapAnnotation::factory()->create();

    $this->actingAs($this->engineer)
        ->from('/map')
        ->patch('/map/positions', ['nodes' => [
            ['kind' => 'site', 'id' => $this->north->id, 'map_x' => 40, 'map_y' => 60],
            ['kind' => 'annotation', 'id' => $zone->id, 'map_x' => 10, 'map_y' => 20],
        ]])
        ->assertSessionHasNoErrors();

    expect($zone->refresh()->map_x)->toBe(10)
        ->and($this->north->refresh()->map_x)->toBe(40);
});

test('a drawing is erased from the map', function () {
    $note = MapAnnotation::factory()->note()->create();

    $this->actingAs($this->engineer)
        ->from('/map')
        ->delete("/map/annotations/{$note->id}")
        ->assertSessionHasNoErrors();

    expect(MapAnnotation::count())->toBe(0);
});

test('a viewer cannot draw on the map', function () {
    $viewer = User::factory()->create(['has_all_sites' => true]);
    $viewer->assignRole('viewer');

    $this->actingAs($viewer)
        ->post('/map/annotations', [
            'type' => 'note',
            'map_x' => 0,
            'map_y' => 0,
            'width' => 220,
            'height' => 120,
        ])
        ->assertForbidden();
});

test('a drawing may not be added to a site the user cannot reach', function () {
    $restricted = User::factory()->create(['has_all_sites' => false]);
    $restricted->assignRole('engineer');
    $restricted->sites()->attach($this->north);

    $this->actingAs($restricted)
        ->post('/map/annotations', [
            'site_id' => $this->south->id,
            'type' => 'zone',
            'map_x' => 0,
            'map_y' => 0,
            'width' => 320,
            'height' => 220,
        ])
        ->assertForbidden();

    expect(MapAnnotation::count())->toBe(0);
});

test('a viewer sees the map but cannot manage tunnels', function () {
    $viewer = User::factory()->create(['has_all_sites' => true]);
    $viewer->assignRole('viewer');

    $this->actingAs($viewer)
        ->get('/map')
        ->assertInertia(fn ($page) => $page->where('can.manage', false));

    $this->actingAs($viewer)
        ->post('/tunnels', [
            'site_a_id' => $this->north->id,
            'site_b_id' => $this->south->id,
            'type' => 'kerio_vpn',
            'status' => 'up',
        ])
        ->assertForbidden();
});

test('a user only sees tunnels touching a site they may access', function () {
    $restricted = User::factory()->create(['has_all_sites' => false]);
    $restricted->assignRole('engineer');
    $restricted->sites()->attach($this->north);

    // A tunnel between two sites the user cannot see stays hidden.
    $other = Site::factory()->create();
    $hidden = Tunnel::factory()->create([
        'site_a_id' => $this->south->id,
        'site_b_id' => $other->id,
    ]);
    $visible = Tunnel::factory()->create([
        'site_a_id' => $this->north->id,
        'site_b_id' => $this->south->id,
    ]);

    $this->actingAs($restricted)
        ->get('/map')
        ->assertInertia(fn ($page) => $page
            ->has('tunnels', 1)
            ->where('tunnels.0.id', $visible->id));
});
