<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Database\Factories\PortFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $device_id
 * @property string $name
 * @property int $number
 * @property string $media
 * @property int|null $speed_mbps
 * @property string $role
 * @property int|null $rear_port_id
 * @property bool $is_uplink
 * @property bool $enabled
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'device_id', 'name', 'number', 'media', 'speed_mbps', 'role',
    'rear_port_id', 'is_uplink', 'enabled', 'description',
])]
class Port extends Model
{
    /** @use HasFactory<PortFactory> */
    use HasFactory, RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_uplink' => 'boolean',
            'enabled' => 'boolean',
        ];
    }

    /**
     * @return list<string>
     */
    protected function auditableAttributes(): array
    {
        return ['name', 'media', 'speed_mbps', 'is_uplink', 'enabled', 'description'];
    }

    /** @return BelongsTo<Device, $this> */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /**
     * The rear port this front port is wired to, inside the same panel.
     *
     * @return BelongsTo<Port, $this>
     */
    public function rearPort(): BelongsTo
    {
        return $this->belongsTo(Port::class, 'rear_port_id');
    }

    /**
     * The front port wired to this rear port.
     *
     * @return HasOne<Port, $this>
     */
    public function frontPort(): HasOne
    {
        return $this->hasOne(Port::class, 'rear_port_id');
    }
}
