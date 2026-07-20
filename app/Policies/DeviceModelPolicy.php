<?php

namespace App\Policies;

use App\Models\DeviceModel;
use App\Models\User;
use App\Support\Permissions;

/**
 * The catalogue is shared by every site, so there is nothing site-scoped here.
 */
class DeviceModelPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::VIEW);
    }

    public function view(User $user, DeviceModel $model): bool
    {
        return $user->can(Permissions::VIEW);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::MANAGE_CATALOG);
    }

    public function update(User $user, DeviceModel $model): bool
    {
        return $user->can(Permissions::MANAGE_CATALOG);
    }

    public function delete(User $user, DeviceModel $model): bool
    {
        return $user->can(Permissions::MANAGE_CATALOG);
    }
}
