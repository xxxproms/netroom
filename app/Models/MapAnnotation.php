<?php

namespace App\Models;

use Database\Factories\MapAnnotationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A drawing on a map: a zone framing the kit of one server room, floor or VLAN
 * domain, or a note pinned beside it. It documents nothing on its own, which is
 * why it is deliberately kept out of the activity log — dragging a note around
 * is housekeeping, not a change to the network.
 *
 * @property int $id
 * @property int|null $site_id
 * @property string $type
 * @property string|null $text
 * @property int $map_x
 * @property int $map_y
 * @property int $width
 * @property int $height
 * @property string|null $color
 * @property int $z
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'site_id', 'type', 'text', 'map_x', 'map_y', 'width', 'height', 'color', 'z',
])]
class MapAnnotation extends Model
{
    /** @use HasFactory<MapAnnotationFactory> */
    use HasFactory;

    public const TYPES = ['zone', 'note'];

    /** The size a fresh drawing gets, before anyone resizes it. */
    public const SIZES = [
        'zone' => ['width' => 320, 'height' => 220],
        'note' => ['width' => 220, 'height' => 120],
    ];

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * A drawing on the global map is visible to everyone who may see the map;
     * one that belongs to a site follows that site's access.
     */
    public function isVisibleTo(User $user): bool
    {
        return $this->site_id === null || $user->canAccessSite($this->site_id);
    }
}
