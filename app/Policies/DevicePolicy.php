<?php

namespace App\Policies;

use App\Models\Device;
use App\Models\User;
use App\Support\Permissions;

class DevicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::VIEW);
    }

    public function view(User $user, Device $device): bool
    {
        return $user->can(Permissions::VIEW) && $user->canAccessSite($device->site_id);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::MANAGE_INFRASTRUCTURE);
    }

    public function update(User $user, Device $device): bool
    {
        return $user->can(Permissions::MANAGE_INFRASTRUCTURE)
            && $user->canAccessSite($device->site_id);
    }

    public function delete(User $user, Device $device): bool
    {
        return $this->update($user, $device);
    }
}
