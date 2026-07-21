<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Database\Factories\DeviceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A physical device: this switch, in this rack, at this site.
 *
 * @property int $id
 * @property int $device_model_id
 * @property int $site_id
 * @property int|null $rack_id
 * @property int|null $position_u
 * @property string $face
 * @property string $name
 * @property string|null $serial
 * @property string|null $mgmt_ip
 * @property string|null $mgmt_url
 * @property string $status
 * @property string|null $color
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'device_model_id', 'site_id', 'rack_id', 'position_u', 'face', 'name',
    'serial', 'mgmt_ip', 'mgmt_url', 'status', 'color', 'notes',
])]
class Device extends Model
{
    /** @use HasFactory<DeviceFactory> */
    use HasFactory, RecordsActivity;

    public const FACES = ['front', 'rear'];

    public const STATUSES = ['active', 'spare', 'failed', 'decommissioned'];

    /**
     * The database cascades a device's ports when it goes, but that skips the
     * model events that clear the cables in them. Deleting the ports through
     * Eloquent first lets those fire.
     */
    protected static function booted(): void
    {
        static::deleting(function (Device $device): void {
            $device->ports->each->delete();
        });
    }

    /**
     * @return list<string>
     */
    protected function auditableAttributes(): array
    {
        return [
            'device_model_id', 'site_id', 'rack_id', 'position_u', 'face',
            'name', 'serial', 'mgmt_ip', 'status', 'color', 'notes',
        ];
    }

    /** @return BelongsTo<DeviceModel, $this> */
    public function deviceModel(): BelongsTo
    {
        return $this->belongsTo(DeviceModel::class);
    }

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /** @return BelongsTo<Rack, $this> */
    public function rack(): BelongsTo
    {
        return $this->belongsTo(Rack::class);
    }

    /** @return HasMany<Port, $this> */
    public function ports(): HasMany
    {
        return $this->hasMany(Port::class)->orderBy('role')->orderBy('number');
    }

    /**
     * The rack units this device occupies, bottom-most first. Empty when the
     * device is not mounted in a rack.
     *
     * @return list<int>
     */
    public function occupiedUnits(): array
    {
        if (! $this->rack_id || ! $this->position_u) {
            return [];
        }

        $height = $this->deviceModel->u_height;

        return range($this->position_u, $this->position_u + $height - 1);
    }
}
