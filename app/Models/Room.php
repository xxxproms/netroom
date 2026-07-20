<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Database\Factories\RoomFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $site_id
 * @property string $name
 * @property string|null $floor
 * @property string $kind
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['site_id', 'name', 'floor', 'kind', 'notes'])]
class Room extends Model
{
    /** @use HasFactory<RoomFactory> */
    use HasFactory, RecordsActivity;

    public const KINDS = ['server_room', 'office', 'hall', 'other'];

    /**
     * @return list<string>
     */
    protected function auditableAttributes(): array
    {
        return ['site_id', 'name', 'floor', 'kind', 'notes'];
    }

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /** @return HasMany<Rack, $this> */
    public function racks(): HasMany
    {
        return $this->hasMany(Rack::class);
    }
}
