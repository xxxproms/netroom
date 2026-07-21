<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Database\Factories\WorkplaceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * The far end of a cable in the real world — a desk, a camera, a till.
 *
 * @property int $id
 * @property int $site_id
 * @property int|null $room_id
 * @property string $name
 * @property string|null $person
 * @property string|null $floor
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['site_id', 'room_id', 'name', 'person', 'floor', 'notes'])]
class Workplace extends Model
{
    /** @use HasFactory<WorkplaceFactory> */
    use HasFactory, RecordsActivity;

    /**
     * Delete the outlets through Eloquent rather than by database cascade, so
     * their cables are cleared by the outlet's own delete event.
     */
    protected static function booted(): void
    {
        static::deleting(function (Workplace $workplace): void {
            $workplace->outlets->each->delete();
        });
    }

    /**
     * @return list<string>
     */
    protected function auditableAttributes(): array
    {
        return ['site_id', 'room_id', 'name', 'person', 'floor', 'notes'];
    }

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /** @return BelongsTo<Room, $this> */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /** @return HasMany<Outlet, $this> */
    public function outlets(): HasMany
    {
        return $this->hasMany(Outlet::class);
    }
}
