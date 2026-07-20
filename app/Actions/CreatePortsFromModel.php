<?php

namespace App\Actions;

use App\Models\Device;
use App\Models\Port;
use App\Models\PortTemplate;
use Illuminate\Support\Facades\DB;

/**
 * Lays out a device's ports from its model's templates, so adding a switch
 * does not mean typing in 28 ports by hand.
 */
class CreatePortsFromModel
{
    public function handle(Device $device): void
    {
        $templates = $device->deviceModel->portTemplates;

        DB::transaction(function () use ($device, $templates) {
            /** @var array<int, Port> $rearPorts */
            $rearPorts = [];

            // Rear ports first: a patch panel's front ports point at them.
            foreach ($templates->where('role', 'rear') as $template) {
                foreach ($this->portsFor($template) as $port) {
                    $created = $device->ports()->create($port);
                    $rearPorts[$created->number] = $created;
                }
            }

            foreach ($templates->where('role', '!=', 'rear') as $template) {
                foreach ($this->portsFor($template) as $port) {
                    $device->ports()->create([
                        ...$port,
                        // Front port 12 of a panel is wired to rear port 12.
                        'rear_port_id' => $template->role === 'front'
                            ? ($rearPorts[$port['number']] ?? null)?->id
                            : null,
                    ]);
                }
            }
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function portsFor(PortTemplate $template): array
    {
        return array_map(fn (array $port) => [
            'name' => $port['name'],
            'number' => $port['number'],
            'media' => $template->media,
            'speed_mbps' => $template->speed_mbps,
            'role' => $template->role,
        ], $template->expand());
    }
}
