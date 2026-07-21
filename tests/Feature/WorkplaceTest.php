<?php

use App\Models\Cable;
use App\Models\Device;
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

test('a workplace is created with the cabling permission', function () {
    $this->actingAs($this->engineer)
        ->post('/workplaces', [
            'site_id' => $this->site->id,
            'name' => 'Каб. 204, место 1',
            'person' => 'Иванова А. С.',
        ])
        ->assertRedirect();

    expect(Workplace::firstWhere('name', 'Каб. 204, место 1')->person)
        ->toBe('Иванова А. С.');
});

test('a workplace name is unique within its site but not across sites', function () {
    Workplace::factory()->create(['site_id' => $this->site->id, 'name' => 'Стол 1']);

    $this->actingAs($this->engineer)
        ->from('/workplaces')
        ->post('/workplaces', ['site_id' => $this->site->id, 'name' => 'Стол 1'])
        ->assertSessionHasErrors('name');

    // The same name at another site is fine.
    $other = Site::factory()->create();

    $this->actingAs($this->engineer)
        ->post('/workplaces', ['site_id' => $other->id, 'name' => 'Стол 1'])
        ->assertSessionHasNoErrors();
});

test('outlets are added to and removed from a workplace', function () {
    $workplace = Workplace::factory()->create(['site_id' => $this->site->id]);

    $this->actingAs($this->engineer)
        ->from("/workplaces/{$workplace->id}")
        ->post("/workplaces/{$workplace->id}/outlets", ['label' => '204-1', 'media' => 'rj45'])
        ->assertSessionHasNoErrors();

    $outlet = $workplace->outlets()->firstWhere('label', '204-1');
    expect($outlet)->not->toBeNull();

    $this->actingAs($this->engineer)
        ->from("/workplaces/{$workplace->id}")
        ->delete("/outlets/{$outlet->id}")
        ->assertSessionHasNoErrors();

    expect(Outlet::find($outlet->id))->toBeNull();
});

test('two outlets at one workplace cannot share a label', function () {
    $workplace = Workplace::factory()->create(['site_id' => $this->site->id]);
    $workplace->outlets()->create(['label' => '204-1', 'media' => 'rj45']);

    $this->actingAs($this->engineer)
        ->from("/workplaces/{$workplace->id}")
        ->post("/workplaces/{$workplace->id}/outlets", ['label' => '204-1', 'media' => 'rj45'])
        ->assertSessionHasErrors('label');
});

test('deleting a workplace takes its outlets and their cables', function () {
    $workplace = Workplace::factory()->create(['site_id' => $this->site->id]);
    $outlet = $workplace->outlets()->create(['label' => '204-1', 'media' => 'rj45']);

    $port = Device::factory()
        ->create(['site_id' => $this->site->id])
        ->ports()
        ->create(['name' => '1', 'number' => 1, 'media' => 'rj45', 'role' => 'network']);

    Cable::factory()->between($port, $outlet)->create(['site_id' => $this->site->id]);

    $this->actingAs($this->engineer)->delete("/workplaces/{$workplace->id}");

    expect(Workplace::count())->toBe(0)
        ->and(Outlet::count())->toBe(0)
        ->and(Cable::count())->toBe(0);
});

test('a workplace at another site is out of reach', function () {
    $workplace = Workplace::factory()->create(['site_id' => $this->site->id]);

    $outsider = User::factory()->create();
    $outsider->assignRole('engineer');

    $this->actingAs($outsider)
        ->get("/workplaces/{$workplace->id}")
        ->assertForbidden();
});

test('the workplace list is scoped to the picked site', function () {
    Workplace::factory()->create(['site_id' => $this->site->id, 'name' => 'Здесь']);
    $other = Site::factory()->create();
    Workplace::factory()->create(['site_id' => $other->id, 'name' => 'Там']);

    $this->actingAs($this->engineer)
        ->withSession(['netroom.site' => $this->site->id])
        ->get('/workplaces')
        ->assertInertia(fn ($page) => $page
            ->has('workplaces', 1)
            ->where('workplaces.0.name', 'Здесь'));
});
