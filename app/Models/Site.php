<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Database\Factories\SiteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A site is the root of everything NetRoom documents: its rooms, racks,
 * devices and workplaces all hang off it.
 *
 * @property int $id
 * @property int $vlan_domain_id
 * @property string $name
 * @property string $code
 * @property string $kind
 * @property string|null $city
 * @property string|null $address
 * @property string|null $color
 * @property int|null $map_x
 * @property int|null $map_y
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'vlan_domain_id', 'name', 'code', 'kind', 'city', 'address', 'color', 'map_x', 'map_y', 'notes',
])]
class Site extends Model
{
    /** @use HasFactory<SiteFactory> */
    use HasFactory, RecordsActivity;

    public const KINDS = ['complex', 'office', 'factory', 'cottage', 'other'];

    /**
     * @return list<string>
     */
    protected function auditableAttributes(): array
    {
        return [
            'vlan_domain_id', 'name', 'code', 'kind', 'city', 'address', 'notes',
        ];
    }

    /** @return BelongsTo<VlanDomain, $this> */
    public function vlanDomain(): BelongsTo
    {
        return $this->belongsTo(VlanDomain::class);
    }

    /** @return HasMany<Room, $this> */
    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
}
