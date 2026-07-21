<?php

use App\Actions\TraceCable;
use App\Models\Cable;
use App\Models\Device;
use App\Models\DeviceModel;
use App\Models\Outlet;
use App\Models\Site;
use App\Models\User;
use App\Models\Workplace;
use Database\Seeders\PermissionSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);

    $this->engineer = User::factory()->create(['has_all_sites' => true]);
    $this->engineer->assignRole('engineer');

    $this->site = Site::factory()->create();
});

/**
 * A switch, a patch panel wired front-to-rear, and a workplace with a socket —
 * the shape every trace test needs.
 */
function estate(Site $site): array
{
    $switchModel = DeviceModel::factory()->create(['kind' => 'switch']);
    $switch = Device::factory()->create([
        'site_id' => $site->id, 'device_model_id' => $switchModel->id, 'name' => 'SW',
    ]);
    $switchPort = $switch->ports()->create([
        'name' => '1', 'number' => 1, 'media' => 'rj45', 'role' => 'network',
    ]);

    $panel = Device::factory()->create([
        'site_id' => $site->id,
        'device_model_id' => DeviceModel::factory()->patchPanel(24)->create()->id,
        'name' => 'PP',
    ]);
    $rear = $panel->ports()->create(['name' => 'R1', 'number' => 1, 'role' => 'rear', 'media' => 'rj45']);
    $front = $panel->ports()->create([
        'name' => 'F1', 'number' => 1, 'role' => 'front', 'media' => 'rj45', 'rear_port_id' => $rear->id,
    ]);

    $workplace = Workplace::factory()->create(['site_id' => $site->id]);
    $outlet = $workplace->outlets()->create(['label' => '204-1', 'media' => 'rj45']);

    return compact('switch', 'switchPort', 'panel', 'front', 'rear', 'outlet', 'workplace');
}

test('a trace runs through a patch panel to the socket', function () {
    ['switchPort' => $port, 'front' => $front, 'rear' => $rear, 'outlet' => $outlet] = estate($this->site);

    Cable::factory()->between($port, $front)->create(['site_id' => $this->site->id]);
    Cable::factory()->between($rear, $outlet)->create(['site_id' => $this->site->id]);

    $path = app(TraceCable::class)->handle($port);

    // port → cable → front → (through panel) rear → cable → outlet
    expect($path)->toHaveCount(6)
        ->and($path[0]['kind'])->toBe('port')
        ->and($path[2]['kind'])->toBe('port')
        ->and($path[2]['role'])->toBe('front')
        ->and($path[3]['kind'])->toBe('port')
        ->and($path[3]['role'])->toBe('rear')
        ->and($path[5]['kind'])->toBe('outlet')
        ->and($path[5]['label'])->toBe('204-1');
});

test('the trace reads the same from the socket end', function () {
    ['switchPort' => $port, 'front' => $front, 'rear' => $rear, 'outlet' => $outlet] = estate($this->site);

    Cable::factory()->between($port, $front)->create(['site_id' => $this->site->id]);
    Cable::factory()->between($rear, $outlet)->create(['site_id' => $this->site->id]);

    $path = app(TraceCable::class)->handle($outlet);

    expect($path[0]['kind'])->toBe('outlet')
        ->and(end($path)['kind'])->toBe('port')
        ->and(end($path)['device']['name'])->toBe('SW');
});

test('an unpatched port traces only to itself', function () {
    ['switchPort' => $port] = estate($this->site);

    expect(app(TraceCable::class)->handle($port))->toHaveCount(1);
});

test('connecting two ports records a cable', function () {
    ['switchPort' => $port, 'front' => $front] = estate($this->site);

    $this->actingAs($this->engineer)
        ->from('/cables')
        ->post('/cables', [
            'a_type' => 'port', 'a_id' => $port->id,
            'b_type' => 'port', 'b_id' => $front->id,
            'media' => 'utp', 'status' => 'connected', 'label' => 'PC-01',
        ])
        ->assertSessionHasNoErrors();

    expect(Cable::where('label', 'PC-01')->exists())->toBeTrue();
});

test('a port already holding a cable will not take a second', function () {
    ['switchPort' => $port, 'front' => $front, 'outlet' => $outlet] = estate($this->site);

    Cable::factory()->between($port, $front)->create(['site_id' => $this->site->id]);

    $this->actingAs($this->engineer)
        ->from('/cables')
        ->post('/cables', [
            'a_type' => 'port', 'a_id' => $port->id,
            'b_type' => 'outlet', 'b_id' => $outlet->id,
            'media' => 'utp', 'status' => 'connected',
        ])
        ->assertSessionHasErrors('a_id');
});

test('fibre must say how many strands it has', function () {
    ['switchPort' => $port, 'front' => $front] = estate($this->site);

    $this->actingAs($this->engineer)
        ->from('/cables')
        ->post('/cables', [
            'a_type' => 'port', 'a_id' => $port->id,
            'b_type' => 'port', 'b_id' => $front->id,
            'media' => 'fibre', 'status' => 'connected',
        ])
        ->assertSessionHasErrors('strands');
});

test('a cable cannot join a port to itself', function () {
    ['switchPort' => $port] = estate($this->site);

    $this->actingAs($this->engineer)
        ->from('/cables')
        ->post('/cables', [
            'a_type' => 'port', 'a_id' => $port->id,
            'b_type' => 'port', 'b_id' => $port->id,
            'media' => 'utp', 'status' => 'connected',
        ])
        ->assertSessionHasErrors('b_id');
});

test('removing a cable frees both of its ends', function () {
    ['switchPort' => $port, 'front' => $front] = estate($this->site);

    $cable = Cable::factory()->between($port, $front)->create(['site_id' => $this->site->id]);

    $this->actingAs($this->engineer)
        ->from('/cables')
        ->delete("/cables/{$cable->id}")
        ->assertSessionHasNoErrors();

    expect($port->refresh()->cable())->toBeNull()
        ->and(Cable::count())->toBe(0);
});

test('a technician can patch cables', function () {
    ['switchPort' => $port, 'front' => $front] = estate($this->site);

    $technician = User::factory()->create(['has_all_sites' => true]);
    $technician->assignRole('technician');

    $this->actingAs($technician)
        ->from('/cables')
        ->post('/cables', [
            'a_type' => 'port', 'a_id' => $port->id,
            'b_type' => 'port', 'b_id' => $front->id,
            'media' => 'utp', 'status' => 'connected',
        ])
        ->assertSessionHasNoErrors();

    expect(Cable::count())->toBe(1);
});

test('a viewer cannot patch cables', function () {
    ['switchPort' => $port, 'front' => $front] = estate($this->site);

    $viewer = User::factory()->create(['has_all_sites' => true]);
    $viewer->assignRole('viewer');

    $this->actingAs($viewer)
        ->post('/cables', [
            'a_type' => 'port', 'a_id' => $port->id,
            'b_type' => 'port', 'b_id' => $front->id,
            'media' => 'utp', 'status' => 'connected',
        ])
        ->assertForbidden();
});

test('the cable journal is scoped to the picked site', function () {
    ['switchPort' => $port, 'front' => $front] = estate($this->site);
    Cable::factory()->between($port, $front)->create(['site_id' => $this->site->id, 'label' => 'HERE']);

    $other = Site::factory()->create();
    ['switchPort' => $p2, 'front' => $f2] = estate($other);
    Cable::factory()->between($p2, $f2)->create(['site_id' => $other->id, 'label' => 'THERE']);

    $this->actingAs($this->engineer)
        ->withSession(['netroom.site' => $this->site->id])
        ->get('/cables')
        ->assertInertia(fn ($page) => $page
            ->has('cables', 1)
            ->where('cables.0.label', 'HERE'));
});
