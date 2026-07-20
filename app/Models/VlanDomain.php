<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Database\Factories\VlanDomainFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'notes'])]
class VlanDomain extends Model
{
    /** @use HasFactory<VlanDomainFactory> */
    use HasFactory, RecordsActivity;

    /**
     * @return list<string>
     */
    protected function auditableAttributes(): array
    {
        return ['name', 'notes'];
    }

    /** @return HasMany<Site, $this> */
    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    /** @return HasMany<Vlan, $this> */
    public function vlans(): HasMany
    {
        return $this->hasMany(Vlan::class);
    }
}
