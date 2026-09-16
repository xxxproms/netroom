<?php

namespace App\Http\Controllers;

use App\Models\Cable;
use App\Models\Device;
use App\Models\Port;
use App\Models\Rack;
use App\Models\Site;
use App\Support\SiteContext;
use App\Support\Terminations;
use Illuminate\Http\JsonResponse;

/**
 * The patching view of a rack: every port on every device in it, with the
 * cable each carries, plus the uplinks that arrive from elsewhere. The
 * elevation page fetches this when commutation mode is switched on, so it is
 * kept out of the initial page load.
 */
class RackPatchController extends Controller
{
    public function __construct(private readonly SiteContext $context) {}

    public function show(Rack $rack): JsonResponse
    {
        $this->authorize('view', $rack);

        $rack->loadMissing('room');

        $devices = Device::where('rack_id', $rack->id)
            ->with([
                'deviceModel:id,vendor,model,kind',
                'ports' => fn ($query) => $query->orderBy('role')->orderBy('number'),
                'ports.cableAsA.b',
                'ports.cableAsB.a',
            ])
            ->orderByDesc('position_u')
            ->get();

        $rackDeviceIds = $devices->pluck('id')->all();

        $externals = [];

        $shaped = $devices->map(function (Device $device) use ($rackDeviceIds, &$externals) {
            $ports = $device->ports->map(function (Port $port) use ($rackDeviceIds, &$externals) {
                $link = Terminations::link($port);

                if ($link !== null && $this->leavesRack($link['far'], $rackDeviceIds)) {
                    $externals[] = [
                        'cable_id' => $link['cable']['id'],
                        'near_port_id' => $port->id,
                        'media' => $link['cable']['media'],
                        'color' => $link['cable']['color'],
                        'status' => $link['cable']['status'],
                        'label' => $link['cable']['label'],
                        'length_cm' => $link['cable']['length_cm'],
                        'far' => $this->externalLabel($link['far']),
                    ];
                }

                return [
                    'id' => $port->id,
                    'name' => $port->name,
                    'number' => $port->number,
                    'media' => $port->media,
                    'role' => $port->role,
                    'is_uplink' => $port->is_uplink,
                    'enabled' => $port->enabled,
                    'link' => $link,
                ];
            })->all();

            return [
                'id' => $device->id,
                'name' => $device->name,
                'kind' => $device->deviceModel->kind,
                'color' => $device->color,
                'position_u' => $device->position_u,
                'u_height' => $device->deviceModel->u_height,
                'face' => $device->face,
                'ports' => $ports,
            ];
        })->all();

        return response()->json([
            'devices' => $shaped,
            'externals' => $externals,
            'statuses' => Cable::STATUSES,
            // The rack's own site is the picker's default; a cross-complex uplink
            // may reach for another, so every site the user can touch is offered.
            'site_id' => $rack->room?->site_id,
            'sites' => $this->context->available()
                ->map(fn (Site $site) => ['id' => $site->id, 'name' => $site->name])
                ->values(),
            'can' => [
                'wire' => request()->user()->can('create', Cable::class),
            ],
        ]);
    }

    /**
     * Whether the far end of a cable sits outside this rack — another rack's
     * device, or a workplace socket. Those are drawn on the uplink rail rather
     * than as a cord between two strips.
     *
     * @param  array<string, mixed>  $far
     * @param  array<int, int>  $rackDeviceIds
     */
    private function leavesRack(array $far, array $rackDeviceIds): bool
    {
        if ($far['kind'] !== 'port') {
            return true;
        }

        return ! in_array($far['device']['id'], $rackDeviceIds, true);
    }

    /**
     * A two-line label for an uplink chip: where it goes, and a hint beneath.
     *
     * @param  array<string, mixed>  $far
     * @return array{label: string, sub: string, port_id: int}
     */
    private function externalLabel(array $far): array
    {
        if ($far['kind'] === 'outlet') {
            return [
                'label' => "{$far['workplace']['name']} · {$far['label']}",
                'sub' => $far['workplace']['room'] ?? $far['workplace']['person'] ?? '',
                'port_id' => $far['id'],
            ];
        }

        return [
            'label' => "{$far['device']['name']} · {$far['name']}",
            'sub' => $far['device']['model'],
            'port_id' => $far['id'],
        ];
    }
}
