<?php

namespace App\Policies;

use App\Models\Room;
use App\Models\User;
use App\Support\Permissions;

class RoomPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::VIEW);
    }

    public function view(User $user, Room $room): bool
    {
        return $user->can(Permissions::VIEW) && $user->canAccessSite($room->site_id);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::MANAGE_INFRASTRUCTURE);
    }

    public function update(User $user, Room $room): bool
    {
        return $user->can(Permissions::MANAGE_INFRASTRUCTURE) && $user->canAccessSite($room->site_id);
    }

    public function delete(User $user, Room $room): bool
    {
        return $this->update($user, $room);
    }
}
