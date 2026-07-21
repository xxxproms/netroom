<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Database\Factories\OutletFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A socket on the wall. One end of a cable plugs in here; the trace stops.
 *
 * @property int $id
 * @property int $workplace_id
 * @property string $label
 * @property string $media
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['workplace_id', 'label', 'media', 'notes'])]
class Outlet extends Model implements Concerns\Terminates
{
    /** @use HasFactory<OutletFactory> */
    use Concerns\TerminatesCables, HasFactory, RecordsActivity;

    public const MEDIA = ['rj45', 'lc', 'sc'];

    /**
     * @return list<string>
     */
    protected function auditableAttributes(): array
    {
        return ['workplace_id', 'label', 'media', 'notes'];
    }

    /** @return BelongsTo<Workplace, $this> */
    public function workplace(): BelongsTo
    {
        return $this->belongsTo(Workplace::class);
    }
}
