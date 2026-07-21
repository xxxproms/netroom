<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use App\Support\Cidr;
use Database\Factories\IpAddressFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A documented address inside a subnet: a reservation, or a note about what
 * lives at an address the panel does not otherwise know.
 *
 * @property int $id
 * @property int $subnet_id
 * @property int $address
 * @property string $address_text
 * @property int|null $device_id
 * @property string|null $hostname
 * @property string $status
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['subnet_id', 'address', 'address_text', 'device_id', 'hostname', 'status', 'notes'])]
class IpAddress extends Model
{
    /** @use HasFactory<IpAddressFactory> */
    use HasFactory, RecordsActivity;

    public const STATUSES = ['reserved', 'assigned', 'gateway', 'excluded'];

    /**
     * @return list<string>
     */
    protected function auditableAttributes(): array
    {
        return ['subnet_id', 'address_text', 'device_id', 'hostname', 'status', 'notes'];
    }

    /**
     * Keeps the numeric address in step with the text it was entered as.
     */
    public function applyAddress(string $address): void
    {
        $long = Cidr::toLong($address);

        if ($long === null) {
            return;
        }

        $this->address = $long;
        $this->address_text = $address;
    }

    /** @return BelongsTo<Subnet, $this> */
    public function subnet(): BelongsTo
    {
        return $this->belongsTo(Subnet::class);
    }

    /** @return BelongsTo<Device, $this> */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
