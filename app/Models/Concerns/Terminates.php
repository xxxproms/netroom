<?php

namespace App\Models\Concerns;

use App\Models\Cable;

/**
 * Something a cable can be plugged into: a device port or a wall outlet.
 */
interface Terminates
{
    /**
     * The cable plugged in here, if there is one.
     */
    public function cable(): ?Cable;
}
