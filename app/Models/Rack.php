<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Database\Factories\RackFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $room_id
 * @property string $name
 * @property int $u_height
 * @property string $kind
 * @property int $sort
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['room_id', 'name', 'u_height', 'kind', 'sort', 'notes'])]
class Rack extends Model
{
    /** @use HasFactory<RackFactory> */
    use HasFactory, RecordsActivity;

    public const KINDS = ['rack', 'wall_cabinet', 'open_frame'];

    /**
     * @return list<string>
     */
    protected function auditableAttributes(): array
    {
        return ['room_id', 'name', 'u_height', 'kind', 'notes'];
    }

    /** @return BelongsTo<Room, $this> */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}
