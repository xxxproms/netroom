<?php

namespace App\Http\Controllers;

use App\Models\Cable;
use App\Models\Device;
use App\Models\Port;
use App\Models\Site;
use App\Models\Tunnel;
use App\Support\SiteContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Two maps in one place: every site and the tunnels between them, and — one
 * level down — the devices inside a single site and the cables joining them.
 */
class NetworkMapController extends Controller
{
    public function __construct(private readonly SiteContext $context) {}

    public function index(): Response
    {
        $this->authorize('viewAny', Tunnel::class);

        $sites = $this->context->available();
        $ids = $sites->pluck('id');

        $sites->loadCount(['rooms', 'devices']);

        $tunnels = Tunnel::with([
            'siteA:id,name,code',
            'siteB:id,name,code',
            'deviceA:id,name',
            'deviceB:id,name',
        ])
            // A tunnel shows if either of its ends is a site the user may see.
            ->where(fn ($query) => $query
                ->whereIn('site_a_id', $ids)
                ->orWhereIn('site_b_id', $ids))
            ->get();

        return Inertia::render('map/Index', [
            'sites' => $sites->map(fn (Site $site) => [
                'id' => $site->id,
                'name' => $site->name,
                'code' => $site->code,
                'kind' => $site->kind,
                'color' => $site->color,
                'map_x' => $site->map_x,
                'map_y' => $site->map_y,
                'rooms_count' => $site->rooms_count,
                'devices_count' => $site->devices_count,
            ])->values(),
            'tunnels' => $tunnels->map(fn (Tunnel $tunnel) => $this->tunnelData($tunnel))->values(),
            'types' => Tunnel::TYPES,
            'statuses' => Tunnel::STATUSES,
            'can' => [
                'manage' => request()->user()->can('create', Tunnel::class),
            ],
        ]);
    }

    public function site(Site $site): Response
    {
        $this->authorize('view', $site);

        $devices = $site->devices()
            ->with('deviceModel:id,vendor,model,kind')
            ->get();

        return Inertia::render('map/Site', [
            'site' => $site->only(['id', 'name', 'code']),
            'devices' => $devices->map(fn (Device $device) => [
                'id' => $device->id,
                'name' => $device->name,
                'kind' => $device->deviceModel->kind,
                'model' => "{$device->deviceModel->vendor} {$device->deviceModel->model}",
                'color' => $device->color,
                'map_x' => $device->map_x,
                'map_y' => $device->map_y,
            ])->values(),
            'links' => $this->intraSiteLinks($site),
            'can' => [
                'arrange' => request()->user()->can('update', $site),
            ],
        ]);
    }

    /**
     * Saves where a site sits on the global map after it is dragged.
     */
    public function moveSite(Request $request, Site $site): RedirectResponse
    {
        $this->authorize('update', $site);

        $site->update($this->position($request));

        return back();
    }

    /**
     * Saves where a device sits on its site map after it is dragged.
     */
    public function moveDevice(Request $request, Device $device): RedirectResponse
    {
        $this->authorize('update', $device);

        $device->update($this->position($request));

        return back();
    }

    /**
     * The cables between two devices of this site — the site's own wiring, with
     * cables that run out to a workplace left off the topology.
     *
     * @return array<int, array<string, mixed>>
     */
    private function intraSiteLinks(Site $site): array
    {
        $portIds = Port::whereHas('device', fn ($query) => $query->where('site_id', $site->id))
            ->pluck('id');

        $cables = Cable::where('site_id', $site->id)
            ->where('a_type', 'port')
            ->where('b_type', 'port')
            ->whereIn('a_id', $portIds)
            ->whereIn('b_id', $portIds)
            ->with(['a.device:id', 'b.device:id'])
            ->get();

        return $cables->map(function (Cable $cable) {
            /** @var Port $a */
            $a = $cable->a;
            /** @var Port $b */
            $b = $cable->b;

            return [
                'id' => $cable->id,
                'a' => $a->device_id,
                'b' => $b->device_id,
                'media' => $cable->media,
                'strands' => $cable->strands,
                'label' => $cable->label,
            ];
        })->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function tunnelData(Tunnel $tunnel): array
    {
        return [
            'id' => $tunnel->id,
            'site_a_id' => $tunnel->site_a_id,
            'site_b_id' => $tunnel->site_b_id,
            'type' => $tunnel->type,
            'status' => $tunnel->status,
            'label' => $tunnel->label,
            'device_a' => $tunnel->deviceA?->only(['id', 'name']),
            'device_b' => $tunnel->deviceB?->only(['id', 'name']),
        ];
    }

    /**
     * @return array{map_x: int, map_y: int}
     */
    private function position(Request $request): array
    {
        return $request->validate([
            'map_x' => ['required', 'integer', 'min:0', 'max:20000'],
            'map_y' => ['required', 'integer', 'min:0', 'max:20000'],
        ]);
    }
}
