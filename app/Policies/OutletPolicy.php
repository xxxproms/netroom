<?php

namespace App\Policies;

use App\Models\Outlet;
use App\Models\User;
use App\Support\Permissions;

/**
 * An outlet has no site of its own: it inherits the one its workplace is at.
 */
class OutletPolicy
{
    public function view(User $user, Outlet $outlet): bool
    {
        return $user->can(Permissions::VIEW)
            && $user->canAccessSite($outlet->workplace->site_id);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::MANAGE_CABLING);
    }

    public function update(User $user, Outlet $outlet): bool
    {
        return $user->can(Permissions::MANAGE_CABLING)
            && $user->canAccessSite($outlet->workplace->site_id);
    }

    public function delete(User $user, Outlet $outlet): bool
    {
        return $this->update($user, $outlet);
    }
}
