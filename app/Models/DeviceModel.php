<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Database\Factories\DeviceModelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A catalogue entry — "DGS-1210-28/ME", not the switch in rack 3. Its port
 * templates describe the ports every such device has.
 *
 * @property int $id
 * @property string $vendor
 * @property string $model
 * @property string $kind
 * @property int $u_height
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['vendor', 'model', 'kind', 'u_height', 'notes'])]
class DeviceModel extends Model
{
    /** @use HasFactory<DeviceModelFactory> */
    use HasFactory, RecordsActivity;

    public const KINDS = [
        'switch', 'patch_panel', 'router', 'firewall', 'server', 'ups', 'other',
    ];

    /**
     * @return list<string>
     */
    protected function auditableAttributes(): array
    {
        return ['vendor', 'model', 'kind', 'u_height', 'notes'];
    }

    /** @return HasMany<PortTemplate, $this> */
    public function portTemplates(): HasMany
    {
        return $this->hasMany(PortTemplate::class)->orderBy('sort');
    }

    /**
     * How many ports a device of this model ends up with.
     */
    public function portCount(): int
    {
        return (int) $this->portTemplates()->sum('count');
    }
}
