<?php

use App\Models\Cable;
use App\Models\Device;
use App\Models\Site;
use App\Models\Tunnel;
use App\Models\User;
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
