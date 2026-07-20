<?php

use App\Support\Permissions;

/**
 * Spatie registers a Gate::before hook that grants an ability outright when
 * the user holds a permission of the same name. A permission called "view"
 * would therefore satisfy $user->can('view', $site) without the policy ever
 * running — and with it the site-scoping every policy relies on.
 */
test('no permission is named after a policy ability', function () {
    $abilities = ['viewAny', 'view', 'create', 'update', 'delete', 'restore', 'forceDelete'];

    expect(array_intersect(Permissions::all(), $abilities))->toBeEmpty();
});
