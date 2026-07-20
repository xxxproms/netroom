<?php

use App\Models\Site;
use App\Models\User;
use App\Models\VlanDomain;
use App\Support\Permissions;
use Database\Seeders\PermissionSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

function engineer(array $attributes = []): User
{
    $user = User::factory()->create($attributes);
    $user->assignRole('engineer');

    return $user;
}

test('a user only sees the sites they were given', function () {
    $allowed = Site::factory()->create();
    $hidden = Site::factory()->create();

    $user = engineer();
    $user->sites()->attach($allowed);

    $this->actingAs($user)
        ->get('/sites')
        ->assertInertia(fn ($page) => $page
            ->has('sites', 1)
            ->where('sites.0.id', $allowed->id));

    $this->actingAs($user)->get("/sites/{$hidden->id}")->assertForbidden();
});

test('a user with all-sites access sees every site', function () {
    Site::factory()->count(3)->create();

    $user = engineer(['has_all_sites' => true]);

    $this->actingAs($user)
        ->get('/sites')
        ->assertInertia(fn ($page) => $page->has('sites', 3));
});

test('an engineer can create a site', function () {
    $domain = VlanDomain::factory()->create();
    $user = engineer(['has_all_sites' => true]);

    $this->actingAs($user)->post('/sites', [
        'vlan_domain_id' => $domain->id,
        'name' => 'Astoria',
        'code' => 'AS',
        'kind' => 'complex',
    ])->assertRedirect();

    expect(Site::where('code', 'AS')->exists())->toBeTrue();
});

test('a viewer cannot create a site', function () {
    $domain = VlanDomain::factory()->create();

    $user = User::factory()->create(['has_all_sites' => true]);
    $user->assignRole('viewer');

    $this->actingAs($user)->post('/sites', [
        'vlan_domain_id' => $domain->id,
        'name' => 'Astoria',
        'code' => 'AS',
        'kind' => 'complex',
    ])->assertForbidden();
});

test('site codes are unique', function () {
    $existing = Site::factory()->create(['code' => 'AS']);
    $user = engineer(['has_all_sites' => true]);

    $this->actingAs($user)
        ->from('/sites')
        ->post('/sites', [
            'vlan_domain_id' => $existing->vlan_domain_id,
            'name' => 'Another',
            'code' => 'AS',
            'kind' => 'complex',
        ])
        ->assertSessionHasErrors('code');
});

test('changes to a site are written to the activity log', function () {
    $site = Site::factory()->create(['name' => 'Old name']);
    $user = engineer(['has_all_sites' => true]);

    $this->actingAs($user)->patch("/sites/{$site->id}", [
        'vlan_domain_id' => $site->vlan_domain_id,
        'name' => 'New name',
        'code' => $site->code,
        'kind' => $site->kind,
    ]);

    $activity = $site->activitiesAsSubject()->latest('id')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($user->id)
        ->and($activity->attribute_changes['old']['name'])->toBe('Old name')
        ->and($activity->attribute_changes['attributes']['name'])->toBe('New name');
});

test('the permission set covers every documented permission', function () {
    expect(Permissions::roles()['admin'])->toEqual(Permissions::all());
});
