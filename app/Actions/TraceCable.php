<?php

namespace App\Actions;

use App\Models\Cable;
use App\Models\Concerns\Terminates;
use App\Models\Port;
use App\Support\Terminations;
use Illuminate\Database\Eloquent\Model;

/**
 * Walks a cable run from one end to the other.
 *
 * A patch panel is not the end of anything: its front port is wired to the
 * rear port of the same number, so the walk hops across the pair and carries
 * on down the next cable. That is what turns "port 12 of SW-N-01" into "the
 * socket by the desk in room 204".
 */
class TraceCable
{
    /** A run that long is a data-entry loop, not a cable. */
    private const MAX_HOPS = 20;

    /**
     * @return list<array<string, mixed>> the path, as ends and the cables between them
     */
    public function handle(Model&Terminates $start): array
    {
        $path = [Terminations::describe($start)];
        $current = $start;
        $seen = [$this->key($start)];

        for ($hop = 0; $hop < self::MAX_HOPS; $hop++) {
            $cable = $current->cable();

            if (! $cable instanceof Cable) {
                break;
            }

            $far = $cable->otherEnd($current);

            // A ring in the data — a cable looping back on a port already seen.
            if (in_array($this->key($far), $seen, true)) {
                break;
            }

            $path[] = Terminations::describeCable($cable);
            $path[] = Terminations::describe($far);
            $seen[] = $this->key($far);

            $through = $far instanceof Port ? $this->throughPanel($far) : null;

            if (! $through instanceof Port || in_array($this->key($through), $seen, true)) {
                break;
            }

            $path[] = Terminations::describe($through);
            $seen[] = $this->key($through);
            $current = $through;
        }

        return $path;
    }

    /**
     * The port on the other side of a patch panel, front to rear or back.
     */
    private function throughPanel(Port $port): ?Port
    {
        if ($port->device->deviceModel->kind !== 'patch_panel') {
            return null;
        }

        return $port->role === 'front'
            ? $port->rearPort
            : $port->frontPort;
    }

    private function key(Model $end): string
    {
        return $end->getMorphClass().':'.$end->getKey();
    }
}
