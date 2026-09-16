<?php

namespace App\Policies;

use App\Models\MapAnnotation;
use App\Models\User;
use App\Support\Permissions;

class MapAnnotationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::VIEW);
    }

    public function view(User $user, MapAnnotation $annotation): bool
    {
        return $user->can(Permissions::VIEW) && $annotation->isVisibleTo($user);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::MANAGE_INFRASTRUCTURE);
    }

    public function update(User $user, MapAnnotation $annotation): bool
    {
        return $user->can(Permissions::MANAGE_INFRASTRUCTURE)
            && $annotation->isVisibleTo($user);
    }

    public function delete(User $user, MapAnnotation $annotation): bool
    {
        return $this->update($user, $annotation);
    }
}
