<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Workplace;
use App\Support\Permissions;

class WorkplacePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::VIEW);
    }

    public function view(User $user, Workplace $workplace): bool
    {
        return $user->can(Permissions::VIEW) && $user->canAccessSite($workplace->site_id);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::MANAGE_CABLING);
    }

    public function update(User $user, Workplace $workplace): bool
    {
        return $user->can(Permissions::MANAGE_CABLING) && $user->canAccessSite($workplace->site_id);
    }

    public function delete(User $user, Workplace $workplace): bool
    {
        return $this->update($user, $workplace);
    }
}
