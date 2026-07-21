<?php

namespace App\Policies;

use App\Models\IpAddress;
use App\Models\User;
use App\Support\Permissions;

/**
 * An address inherits the reach of the subnet it sits in.
 */
class IpAddressPolicy
{
    public function create(User $user): bool
    {
        return $user->can(Permissions::MANAGE_IPAM);
    }

    public function update(User $user, IpAddress $address): bool
    {
        return $user->can(Permissions::MANAGE_IPAM) && $address->subnet->isVisibleTo($user);
    }

    public function delete(User $user, IpAddress $address): bool
    {
        return $this->update($user, $address);
    }
}
