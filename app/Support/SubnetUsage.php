<?php

namespace App\Support;

use App\Models\Device;
use App\Models\Subnet;
use Illuminate\Support\Collection;

/**
 * What is actually living in a subnet. Two sources are merged: the addresses a
 * user documented by hand, and the management IPs of devices that happen to
 * fall inside the range — so a switch's address shows up the moment it is
 * entered on the device, without anyone re-typing it here.
 */
final class SubnetUsage
{
    /**
     * @return array{
     *     capacity: int,
     *     used: int,
     *     free: int,
     *     utilisation: int,
     *     occupants: list<array<string, mixed>>,
     *     conflicts: int
     * }
     */
    public function summarise(Subnet $subnet): array
    {
        $occupants = $this->occupants($subnet);
        $capacity = $subnet->range()->hostCount();
        $used = count($occupants);
        $conflicts = count(array_filter($occupants, fn (array $entry) => $entry['conflict']));

        return [
            'capacity' => $capacity,
            'used' => $used,
            'free' => max(0, $capacity - $used),
            'utilisation' => $capacity > 0 ? (int) round($used / $capacity * 100) : 0,
            'occupants' => $occupants,
            'conflicts' => $conflicts,
        ];
    }

    /**
     * The occupied addresses, sorted, each tagged with where it came from and
     * whether two things claim it.
     *
     * @return list<array<string, mixed>>
     */
    public function occupants(Subnet $subnet): array
    {
        $range = $subnet->range();

        /** @var array<int, array<string, mixed>> $byAddress */
        $byAddress = [];

        foreach ($this->devices($subnet) as $device) {
            $long = Cidr::toLong((string) $device->mgmt_ip);

            if ($long === null || ! $range->contains($long)) {
                continue;
            }

            $byAddress[$long][] = [
                'source' => 'device',
                'device' => ['id' => $device->id, 'name' => $device->name],
                'hostname' => $device->name,
                'status' => 'assigned',
            ];
        }

        foreach ($subnet->addresses as $reservation) {
            $byAddress[$reservation->address][] = [
                'source' => 'reservation',
                'id' => $reservation->id,
                'device' => $reservation->device_id !== null
                    ? ['id' => $reservation->device_id, 'name' => $reservation->device?->name]
                    : null,
                'hostname' => $reservation->hostname,
                'status' => $reservation->status,
            ];
        }

        ksort($byAddress);

        $occupants = [];

        foreach ($byAddress as $long => $claims) {
            $occupants[] = [
                'address' => Cidr::toString($long),
                'long' => $long,
                'is_gateway' => $subnet->gateway !== null
                    && Cidr::toLong($subnet->gateway) === $long,
                // Two independent claims on one address is the classic mistake.
                'conflict' => $this->isConflict(array_values($claims)),
                'claims' => $claims,
            ];
        }

        return $occupants;
    }

    /**
     * The first host address with nothing on it, or null when the subnet is
     * full — what "give me a free address" answers.
     */
    public function nextFree(Subnet $subnet): ?string
    {
        $range = $subnet->range();
        $taken = array_column($this->occupants($subnet), 'long');
        $taken = array_flip($taken);

        for ($long = $range->firstHost(); $long <= $range->lastHost(); $long++) {
            if (! isset($taken[$long])) {
                return Cidr::toString($long);
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $claims
     */
    private function isConflict(array $claims): bool
    {
        if (count($claims) < 2) {
            return false;
        }

        // A reservation pointed at the very device that owns the address is not
        // a conflict — it is the reservation documenting that device.
        $deviceIds = array_filter(array_map(
            fn (array $claim) => $claim['device']['id'] ?? null,
            $claims,
        ));

        return count(array_unique($deviceIds)) !== 1 || count($claims) !== count($deviceIds);
    }

    /**
     * @return Collection<int, Device>
     */
    private function devices(Subnet $subnet)
    {
        return Device::whereNotNull('mgmt_ip')
            ->whereHas('site', fn ($query) => $query->where('vlan_domain_id', $subnet->vlan_domain_id))
            ->get(['id', 'name', 'mgmt_ip', 'site_id']);
    }
}
