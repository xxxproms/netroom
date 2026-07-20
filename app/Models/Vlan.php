<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Database\Factories\VlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $vlan_domain_id
 * @property int $vid
 * @property string $name
 * @property string|null $description
 * @property string|null $color
 * @property-read PortVlan $pivot
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['vlan_domain_id', 'vid', 'name', 'description', 'color'])]
class Vlan extends Model
{
    /** @use HasFactory<VlanFactory> */
    use HasFactory, RecordsActivity;

    /**
     * @return list<string>
     */
    protected function auditableAttributes(): array
    {
        return ['vlan_domain_id', 'vid', 'name', 'description'];
    }

    /** @return BelongsTo<VlanDomain, $this> */
    public function vlanDomain(): BelongsTo
    {
        return $this->belongsTo(VlanDomain::class);
    }

    /**
     * The ports carrying this VLAN, across every switch that has it.
     *
     * @return BelongsToMany<Port, $this, PortVlan, 'pivot'>
     */
    public function ports(): BelongsToMany
    {
        return $this->belongsToMany(Port::class)
            ->using(PortVlan::class)
            ->withPivot('mode')
            ->withTimestamps();
    }

    /**
     * The VLANs available at a site — that is, its domain's plan.
     *
     * @param  Builder<Vlan>  $query
     */
    public function scopeForSite(Builder $query, Site $site): void
    {
        $query->where('vlan_domain_id', $site->vlan_domain_id);
    }
}
