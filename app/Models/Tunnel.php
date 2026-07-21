<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Database\Factories\TunnelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A site-to-site link across the internet.
 *
 * @property int $id
 * @property int $site_a_id
 * @property int $site_b_id
 * @property int|null $device_a_id
 * @property int|null $device_b_id
 * @property string $type
 * @property string $status
 * @property string|null $label
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'site_a_id', 'site_b_id', 'device_a_id', 'device_b_id',
    'type', 'status', 'label', 'notes',
])]
class Tunnel extends Model
{
    /** @use HasFactory<TunnelFactory> */
    use HasFactory, RecordsActivity;

    /** Kerio Control terminates a VPN; a MikroTik terminates IPsec. */
    public const TYPES = ['kerio_vpn', 'ipsec', 'other'];

    public const STATUSES = ['up', 'down', 'planned'];

    /**
     * @return list<string>
     */
    protected function auditableAttributes(): array
    {
        return ['site_a_id', 'site_b_id', 'device_a_id', 'device_b_id', 'type', 'status', 'label', 'notes'];
    }

    /** @return BelongsTo<Site, $this> */
    public function siteA(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'site_a_id');
    }

    /** @return BelongsTo<Site, $this> */
    public function siteB(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'site_b_id');
    }

    /** @return BelongsTo<Device, $this> */
    public function deviceA(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_a_id');
    }

    /** @return BelongsTo<Device, $this> */
    public function deviceB(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_b_id');
    }

    /**
     * A user sees a tunnel if they may see either of the sites it joins.
     */
    public function isVisibleTo(User $user): bool
    {
        return $user->canAccessSite($this->site_a_id)
            || $user->canAccessSite($this->site_b_id);
    }
}
