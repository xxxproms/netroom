<?php

namespace App\Models\Concerns;

use App\Models\Cable;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * A cable stores its two ends as A and B, and which end a port happens to be
 * on is an accident of how it was entered. This hides that: whoever holds the
 * cable asks for it the same way from either side.
 */
trait TerminatesCables
{
    /**
     * A cable points at its ends polymorphically, so the database has no
     * foreign key to cascade. When an end is deleted we clear its cable here,
     * before the row it references is gone.
     */
    public static function bootTerminatesCables(): void
    {
        static::deleting(function (self $end): void {
            $end->cableAsA()->delete();
            $end->cableAsB()->delete();
        });
    }

    /** @return MorphOne<Cable, $this> */
    public function cableAsA(): MorphOne
    {
        return $this->morphOne(Cable::class, 'a');
    }

    /** @return MorphOne<Cable, $this> */
    public function cableAsB(): MorphOne
    {
        return $this->morphOne(Cable::class, 'b');
    }

    public function cable(): ?Cable
    {
        return $this->cableAsA ?? $this->cableAsB;
    }
}
