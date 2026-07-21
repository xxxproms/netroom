<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use App\Support\Cidr;
use Database\Factories\SubnetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $vlan_domain_id
 * @property int|null $vlan_id
 * @property string $cidr
 * @property int $network
 * @property int $broadcast
 * @property string|null $name
 * @property string|null $gateway
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['vlan_domain_id', 'vlan_id', 'cidr', 'network', 'broadcast', 'name', 'gateway', 'notes'])]
class Subnet extends Model
{
    /** @use HasFactory<SubnetFactory> */
    use HasFactory, RecordsActivity;

    /**
     * @return list<string>
     */
    protected function auditableAttributes(): array
    {
        return ['vlan_domain_id', 'vlan_id', 'cidr', 'name', 'gateway', 'notes'];
    }

    /**
     * Keeps the integer bounds in step with the text whenever the CIDR is set,
     * so containment stays a plain numeric comparison.
     */
    public function applyCidr(string $cidr): void
    {
        $parsed = Cidr::parse($cidr);

        if ($parsed === null) {
            return;
        }

        $this->cidr = $parsed->label();
        $this->network = $parsed->network;
        $this->broadcast = $parsed->broadcast();
    }

    public function range(): Cidr
    {
        return new Cidr($this->network, (int) explode('/', $this->cidr)[1]);
    }

    /** @return BelongsTo<VlanDomain, $this> */
    public function vlanDomain(): BelongsTo
    {
        return $this->belongsTo(VlanDomain::class);
    }

    /** @return BelongsTo<Vlan, $this> */
    public function vlan(): BelongsTo
    {
        return $this->belongsTo(Vlan::class);
    }

    /** @return HasMany<IpAddress, $this> */
    public function addresses(): HasMany
    {
        return $this->hasMany(IpAddress::class);
    }

    /**
     * A subnet is a user's to see if they may reach any site on its VLAN plan.
     */
    public function isVisibleTo(User $user): bool
    {
        if ($user->has_all_sites) {
            return true;
        }

        return $user->sites()
            ->where('vlan_domain_id', $this->vlan_domain_id)
            ->exists();
    }
}
