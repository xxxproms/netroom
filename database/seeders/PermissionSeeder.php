<?php

namespace Database\Seeders;

use App\Support\Permissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    /**
     * Creates the permissions and default roles. Safe to run again: it only
     * adds what is missing and never strips permissions an administrator
     * granted a role by hand.
     */
    public function run(): void
    {
        // Both the "does it exist" lookup and givePermissionTo read a cached
        // set, so it is dropped before each step rather than once up front.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Permissions::all() as $permission) {
            Permission::findOrCreate($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Permissions::roles() as $name => $permissions) {
            Role::findOrCreate($name)->givePermissionTo($permissions);
        }
    }
}
