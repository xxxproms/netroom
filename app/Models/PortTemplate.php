<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Database\Factories\PortTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One run of identical ports on a model, e.g. 24 copper ports numbered 1-24,
 * or 4 SFP slots numbered 25-28.
 *
 * @property int $id
 * @property int $device_model_id
 * @property string $name_prefix
 * @property int $start_number
 * @property int $count
 * @property string $media
 * @property int|null $speed_mbps
 * @property string $role
 * @property int $sort
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'device_model_id', 'name_prefix', 'start_number', 'count', 'media', 'speed_mbps', 'role', 'sort',
])]
class PortTemplate extends Model
{
    /** @use HasFactory<PortTemplateFactory> */
    use HasFactory, RecordsActivity;

    /** Copper, SFP cage, or the two common fibre connector types. */
    public const MEDIA = ['rj45', 'sfp', 'sfp_plus', 'lc', 'sc'];

    /** Patch panels have front and rear ports; everything else is a network port. */
    public const ROLES = ['network', 'front', 'rear'];

    /**
     * @return list<string>
     */
    protected function auditableAttributes(): array
    {
        return [
            'name_prefix', 'start_number', 'count', 'media', 'speed_mbps', 'role',
        ];
    }

    /** @return BelongsTo<DeviceModel, $this> */
    public function deviceModel(): BelongsTo
    {
        return $this->belongsTo(DeviceModel::class);
    }

    /**
     * The port names this template expands into.
     *
     * @return list<array{name: string, number: int}>
     */
    public function expand(): array
    {
        $ports = [];

        for ($offset = 0; $offset < $this->count; $offset++) {
            $number = $this->start_number + $offset;

            $ports[] = [
                'name' => $this->name_prefix.$number,
                'number' => $number,
            ];
        }

        return $ports;
    }
}
