<?php

namespace App\Policies;

use App\Models\Tunnel;
use App\Models\User;
use App\Support\Permissions;

class TunnelPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::VIEW);
    }

    public function view(User $user, Tunnel $tunnel): bool
    {
        return $user->can(Permissions::VIEW) && $tunnel->isVisibleTo($user);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::MANAGE_INFRASTRUCTURE);
    }

    public function update(User $user, Tunnel $tunnel): bool
    {
        return $user->can(Permissions::MANAGE_INFRASTRUCTURE) && $tunnel->isVisibleTo($user);
    }

    public function delete(User $user, Tunnel $tunnel): bool
    {
        return $this->update($user, $tunnel);
    }
}
