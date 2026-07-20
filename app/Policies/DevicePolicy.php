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

    /**
     * Editing the VLAN matrix is a network change, not a rack change: it is
     * the VLAN permission that decides, not the infrastructure one.
     */
    public function updateVlans(User $user, Device $device): bool
    {
        return $user->can(Permissions::MANAGE_VLANS)
            && $user->canAccessSite($device->site_id);
    }

    public function delete(User $user, Device $device): bool
    {
        return $this->update($user, $device);
    }
}
