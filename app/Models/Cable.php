<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use App\Models\Concerns\Terminates;
use Database\Factories\CableFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * One run of cable between two ends.
 *
 * @property int $id
 * @property int $site_id
 * @property string $a_type
 * @property int $a_id
 * @property string $b_type
 * @property int $b_id
 * @property string $media
 * @property int|null $strands
 * @property string|null $label
 * @property int|null $length_cm
 * @property string|null $color
 * @property string $status
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read (Model&Terminates) $a
 * @property-read (Model&Terminates) $b
 */
#[Fillable([
    'site_id', 'a_type', 'a_id', 'b_type', 'b_id', 'media', 'strands',
    'label', 'length_cm', 'color', 'status', 'notes',
])]
class Cable extends Model
{
    /** @use HasFactory<CableFactory> */
    use HasFactory, RecordsActivity;

    public const MEDIA = ['utp', 'fibre'];

    public const STATUSES = ['connected', 'planned', 'decommissioned'];

    /** How many strands a fibre run may have. Copper has none. */
    public const STRANDS = [1, 2];

    /**
     * @return list<string>
     */
    protected function auditableAttributes(): array
    {
        return [
            'a_type', 'a_id', 'b_type', 'b_id', 'media', 'strands',
            'label', 'length_cm', 'status', 'notes',
        ];
    }

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /** @return MorphTo<Model, $this> */
    public function a(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return MorphTo<Model, $this> */
    public function b(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The end opposite the one given — how a trace steps across the cable.
     */
    public function otherEnd(Model&Terminates $end): Model&Terminates
    {
        return $end->is($this->a) ? $this->b : $this->a;
    }
}
