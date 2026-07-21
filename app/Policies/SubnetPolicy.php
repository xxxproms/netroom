<?php

namespace App\Policies;

use App\Models\Subnet;
use App\Models\User;
use App\Support\Permissions;

class SubnetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::VIEW);
    }

    public function view(User $user, Subnet $subnet): bool
    {
        return $user->can(Permissions::VIEW) && $subnet->isVisibleTo($user);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::MANAGE_IPAM);
    }

    public function update(User $user, Subnet $subnet): bool
    {
        return $user->can(Permissions::MANAGE_IPAM) && $subnet->isVisibleTo($user);
    }

    public function delete(User $user, Subnet $subnet): bool
    {
        return $this->update($user, $subnet);
    }
}
