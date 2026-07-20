<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VlanDomain;
use App\Support\Permissions;

class VlanDomainPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::VIEW);
    }

    public function view(User $user, VlanDomain $domain): bool
    {
        return $user->can(Permissions::VIEW);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::MANAGE_VLANS);
    }

    public function update(User $user, VlanDomain $domain): bool
    {
        return $user->can(Permissions::MANAGE_VLANS);
    }

    public function delete(User $user, VlanDomain $domain): bool
    {
        return $user->can(Permissions::MANAGE_VLANS);
    }
}
