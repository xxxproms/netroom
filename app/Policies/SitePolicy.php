<?php

namespace App\Policies;

use App\Models\Site;
use App\Models\User;
use App\Support\Permissions;

class SitePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::VIEW);
    }

    public function view(User $user, Site $site): bool
    {
        return $user->can(Permissions::VIEW) && $user->canAccessSite($site);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::MANAGE_INFRASTRUCTURE);
    }

    public function update(User $user, Site $site): bool
    {
        return $user->can(Permissions::MANAGE_INFRASTRUCTURE) && $user->canAccessSite($site);
    }

    public function delete(User $user, Site $site): bool
    {
        return $this->update($user, $site);
    }
}
