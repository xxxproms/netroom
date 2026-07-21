<?php

namespace App\Policies;

use App\Models\Cable;
use App\Models\User;
use App\Support\Permissions;

class CablePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::VIEW);
    }

    public function view(User $user, Cable $cable): bool
    {
        return $user->can(Permissions::VIEW) && $user->canAccessSite($cable->site_id);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::MANAGE_CABLING);
    }

    public function update(User $user, Cable $cable): bool
    {
        return $user->can(Permissions::MANAGE_CABLING) && $user->canAccessSite($cable->site_id);
    }

    public function delete(User $user, Cable $cable): bool
    {
        return $this->update($user, $cable);
    }
}
