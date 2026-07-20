<?php

use App\Models\User;

test('the registration screen is unavailable by default', function () {
    config()->set('netroom.allow_registration', false);

    $this->get('/register')->assertForbidden();
});

test('registration cannot be submitted while it is disabled', function () {
    config()->set('netroom.allow_registration', false);

    $this->post('/register', [
        'name' => 'Uninvited',
        'email' => 'uninvited@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertForbidden();

    expect(User::where('email', 'uninvited@example.com')->exists())->toBeFalse();
});

test('an administrator can open registration back up', function () {
    config()->set('netroom.allow_registration', true);

    $this->get('/register')->assertOk();
});
