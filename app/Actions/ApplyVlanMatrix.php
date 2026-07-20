<?php

namespace App\Actions;

use App\Models\Port;
use Illuminate\Support\Facades\DB;

/**
 * Writes a batch of matrix changes as a switch would understand them.
 */
class ApplyVlanMatrix
{
    /**
     * @param  list<array{port_id: int, vlan_id: int, mode?: string|null}>  $changes
     * @return int the number of memberships that actually changed
     */
    public function handle(array $changes): int
    {
        return DB::transaction(function () use ($changes) {
            $ports = Port::with('vlans')
                ->findMany(array_unique(array_column($changes, 'port_id')))
                ->keyBy('id');

            $applied = 0;

            foreach ($changes as $change) {
                $port = $ports[$change['port_id']] ?? null;

                if (! $port instanceof Port) {
                    continue;
                }

                $applied += $this->apply($port, $change['vlan_id'], $change['mode'] ?? null);
            }

            return $applied;
        });
    }

    /**
     * @return int 1 if the port's membership changed, 0 if it already matched
     */
    private function apply(Port $port, int $vlanId, ?string $mode): int
    {
        $current = $port->vlans->firstWhere('id', $vlanId)?->pivot->mode;

        if ($current === $mode) {
            return 0;
        }

        if ($mode === null) {
            $port->vlans()->detach($vlanId);
            $port->load('vlans');

            return 1;
        }

        // A port has one PVID, so a second untagged VLAN replaces the first —
        // the same thing a switch does when you move an access port.
        if ($mode === 'untagged') {
            $replaced = $port->vlans
                ->filter(fn ($vlan) => $vlan->pivot->mode === 'untagged' && $vlan->id !== $vlanId)
                ->pluck('id');

            $port->vlans()->detach($replaced);
        }

        $port->vlans()->syncWithoutDetaching([$vlanId => ['mode' => $mode]]);

        // Later changes in the same batch must see what this one just did.
        $port->load('vlans');

        return 1;
    }
}
