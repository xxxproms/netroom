<?php

namespace App\Policies;

use App\Models\Rack;
use App\Models\User;
use App\Support\Permissions;

class RackPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::VIEW);
    }

    public function view(User $user, Rack $rack): bool
    {
        return $user->can(Permissions::VIEW) && $user->canAccessSite($rack->room->site_id);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::MANAGE_INFRASTRUCTURE);
    }

    public function update(User $user, Rack $rack): bool
    {
        return $user->can(Permissions::MANAGE_INFRASTRUCTURE)
            && $user->canAccessSite($rack->room->site_id);
    }

    public function delete(User $user, Rack $rack): bool
    {
        return $this->update($user, $rack);
    }
}
