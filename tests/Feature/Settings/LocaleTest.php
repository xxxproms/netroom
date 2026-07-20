<?php

use App\Models\User;

test('the interface language is shared with every page', function () {
    $user = User::factory()->create(['locale' => 'en']);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page->where('locale', 'en'));
});

test('a user without a preference gets the application default', function () {
    config()->set('app.locale', 'ru');

    $user = User::factory()->create(['locale' => null]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page->where('locale', 'ru'));
});

test('a user can switch the interface language', function () {
    $user = User::factory()->create(['locale' => 'ru']);

    $this->actingAs($user)
        ->from('/settings/appearance')
        ->patch('/settings/language', ['locale' => 'en'])
        ->assertRedirect('/settings/appearance');

    expect($user->refresh()->locale)->toBe('en');
});

test('an unsupported language is rejected', function () {
    $user = User::factory()->create(['locale' => 'ru']);

    $this->actingAs($user)
        ->from('/settings/appearance')
        ->patch('/settings/language', ['locale' => 'de'])
        ->assertSessionHasErrors('locale');

    expect($user->refresh()->locale)->toBe('ru');
});

test('guests cannot change the language', function () {
    $this->patch('/settings/language', ['locale' => 'en'])
        ->assertRedirect('/login');
});
