<?php

namespace App\Http\Controllers;

use App\Models\Cable;
use App\Models\Device;
use App\Models\MapAnnotation;
use App\Models\Port;
use App\Models\Site;
use App\Models\Tunnel;
use App\Support\SiteContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
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
        $sites->load('vlanDomain:id,name');

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
                'vlan_domain' => $site->vlanDomain?->name,
            ])->values(),
            'tunnels' => $tunnels->map(fn (Tunnel $tunnel) => $this->tunnelData($tunnel))->values(),
            'annotations' => $this->annotations(null),
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
            ->with(['deviceModel:id,vendor,model,kind', 'rack:id,name'])
            ->withCount('ports')
            ->get();

        $vlans = $this->deviceVlans($site);

        return Inertia::render('map/Site', [
            'site' => $site->only(['id', 'name', 'code']),
            'devices' => $devices->map(fn (Device $device) => [
                'id' => $device->id,
                'name' => $device->name,
                'kind' => $device->deviceModel->kind,
                'model' => "{$device->deviceModel->vendor} {$device->deviceModel->model}",
                'status' => $device->status,
                'ports_count' => $device->ports_count,
                'mgmt_ip' => $device->mgmt_ip,
                'color' => $device->color,
                'map_x' => $device->map_x,
                'map_y' => $device->map_y,
                'rack' => $device->rack?->only(['id', 'name']),
                'vlans' => $vlans[$device->id] ?? [],
            ])->values(),
            'links' => $this->intraSiteLinks($site),
            'annotations' => $this->annotations($site),
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
     * Renames a site and recolours it straight from the map — a light touch that
     * skips the full site form, so tidying the diagram stays on one screen.
     */
    public function styleSite(Request $request, Site $site): RedirectResponse
    {
        $this->authorize('update', $site);

        $site->update($request->validate([
            'name' => ['required', 'string', 'max:120'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]));

        return back();
    }

    /**
     * Renames and recolours a device from the map, keeping names unique within
     * the site just as the full device form does.
     */
    public function styleDevice(Request $request, Device $device): RedirectResponse
    {
        $this->authorize('update', $device);

        $device->update($request->validate([
            'name' => [
                'required', 'string', 'max:120',
                Rule::unique('devices')
                    ->where('site_id', $device->site_id)
                    ->ignore($device),
            ],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]));

        return back();
    }

    /**
     * Saves a whole batch of node positions in one request — what auto-layout,
     * align/distribute, nudging and layout undo all lean on so the map does not
     * fire one round-trip per node. Each node is authorised on its own.
     */
    public function positions(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nodes' => ['required', 'array', 'max:1000'],
            'nodes.*.kind' => ['required', Rule::in(['site', 'device', 'annotation'])],
            'nodes.*.id' => ['required', 'integer'],
            'nodes.*.map_x' => ['required', 'integer', 'min:0', 'max:20000'],
            'nodes.*.map_y' => ['required', 'integer', 'min:0', 'max:20000'],
        ]);

        foreach ($data['nodes'] as $node) {
            $id = (int) $node['id'];

            $model = match ($node['kind']) {
                'site' => Site::find($id),
                'device' => Device::find($id),
                default => MapAnnotation::find($id),
            };

            if (! $model) {
                continue;
            }

            $this->authorize('update', $model);

            $model->update([
                'map_x' => (int) $node['map_x'],
                'map_y' => (int) $node['map_y'],
            ]);
        }

        return back();
    }

    /**
     * Drops a zone or a note on a map. A zone frames the kit that shares a
     * server room, a floor or a VLAN domain; a note explains what the diagram
     * cannot say on its own.
     */
    public function storeAnnotation(Request $request): RedirectResponse
    {
        $this->authorize('create', MapAnnotation::class);

        $data = $request->validate($this->annotationRules() + [
            'site_id' => ['nullable', 'integer', 'exists:sites,id'],
            'type' => ['required', Rule::in(MapAnnotation::TYPES)],
        ]);

        // A site drawing may only be added by someone who may arrange that site.
        if (! empty($data['site_id'])) {
            $this->authorize('update', Site::findOrFail((int) $data['site_id']));
        }

        MapAnnotation::create($data);

        return back();
    }

    /**
     * Moves, resizes, retitles or recolours a drawing — the map sends whichever
     * of those it changed, so dragging never overwrites the text.
     */
    public function updateAnnotation(Request $request, MapAnnotation $annotation): RedirectResponse
    {
        $this->authorize('update', $annotation);

        $annotation->update($request->validate($this->annotationRules(sometimes: true)));

        return back();
    }

    public function destroyAnnotation(MapAnnotation $annotation): RedirectResponse
    {
        $this->authorize('delete', $annotation);

        $annotation->delete();

        return back();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function annotationRules(bool $sometimes = false): array
    {
        $when = fn (array $rules) => $sometimes ? array_merge(['sometimes'], $rules) : $rules;

        return [
            'text' => $when(['nullable', 'string', 'max:200']),
            'map_x' => $when(['required', 'integer', 'min:0', 'max:20000']),
            'map_y' => $when(['required', 'integer', 'min:0', 'max:20000']),
            'width' => $when(['required', 'integer', 'min:60', 'max:20000']),
            'height' => $when(['required', 'integer', 'min:40', 'max:20000']),
            'color' => $when(['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/']),
            // Always optional: the column has a default, and a drag never sends it.
            'z' => ['sometimes', 'integer', 'min:0', 'max:100'],
        ];
    }

    /**
     * The drawings of one map: a null site means the global one.
     *
     * @return array<int, array<string, mixed>>
     */
    private function annotations(?Site $site): array
    {
        return MapAnnotation::query()
            ->when(
                $site,
                fn ($query) => $query->where('site_id', $site->id),
                fn ($query) => $query->whereNull('site_id'),
            )
            ->orderBy('z')
            ->orderBy('id')
            ->get()
            ->map(fn (MapAnnotation $annotation) => [
                'id' => $annotation->id,
                'type' => $annotation->type,
                'text' => $annotation->text,
                'map_x' => $annotation->map_x,
                'map_y' => $annotation->map_y,
                'width' => $annotation->width,
                'height' => $annotation->height,
                'color' => $annotation->color,
                'z' => $annotation->z,
            ])
            ->values()
            ->all();
    }

    /**
     * The VLAN ids carried by each device of this site, keyed by device. Read in
     * one query rather than through the ports so that a site with a few thousand
     * ports still renders its map without a fuss.
     *
     * @return array<int, list<int>>
     */
    private function deviceVlans(Site $site): array
    {
        $rows = DB::table('port_vlan')
            ->join('ports', 'ports.id', '=', 'port_vlan.port_id')
            ->join('vlans', 'vlans.id', '=', 'port_vlan.vlan_id')
            ->join('devices', 'devices.id', '=', 'ports.device_id')
            ->where('devices.site_id', $site->id)
            ->distinct()
            ->orderBy('vlans.vid')
            ->get(['ports.device_id as device_id', 'vlans.vid as vid']);

        $vlans = [];

        foreach ($rows as $row) {
            $vlans[(int) $row->device_id][] = (int) $row->vid;
        }

        return $vlans;
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
