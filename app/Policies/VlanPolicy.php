<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vlan;
use App\Support\Permissions;

class VlanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::VIEW);
    }

    public function view(User $user, Vlan $vlan): bool
    {
        return $user->can(Permissions::VIEW) && $this->reachesDomain($user, $vlan);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::MANAGE_VLANS);
    }

    public function update(User $user, Vlan $vlan): bool
    {
        return $user->can(Permissions::MANAGE_VLANS) && $this->reachesDomain($user, $vlan);
    }

    public function delete(User $user, Vlan $vlan): bool
    {
        return $this->update($user, $vlan);
    }

    /**
     * A VLAN plan belongs to a domain, so a user reaches it through any site
     * of that domain they have access to.
     */
    private function reachesDomain(User $user, Vlan $vlan): bool
    {
        if ($user->has_all_sites) {
            return true;
        }

        return $user->sites()->where('vlan_domain_id', $vlan->vlan_domain_id)->exists();
    }
}
