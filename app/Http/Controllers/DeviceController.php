<?php

namespace App\Http\Controllers;

use App\Actions\CreatePortsFromModel;
use App\Http\Requests\DeviceRequest;
use App\Models\Device;
use App\Models\DeviceModel;
use App\Models\Port;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Site;
use App\Support\SiteContext;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DeviceController extends Controller
{
    public function __construct(private readonly SiteContext $context) {}

    public function index(): Response
    {
        $this->authorize('viewAny', Device::class);

        $devices = $this->context->scope(Device::query())
            ->with(['deviceModel:id,vendor,model,kind', 'site:id,name,code', 'rack:id,name'])
            ->withCount('ports')
            ->orderBy('name')
            ->get()
            ->map(fn (Device $device) => $this->summarise($device));

        return Inertia::render('devices/Index', [
            'devices' => $devices,
            'statuses' => Device::STATUSES,
            'can' => [
                'create' => request()->user()->can('create', Device::class),
            ],
        ]);
    }

    public function show(Device $device): Response
    {
        $this->authorize('view', $device);

        $device->load([
            'deviceModel',
            'site:id,name,code',
            'rack:id,name,room_id',
            'rack.room:id,name',
            'ports' => fn ($query) => $query->orderBy('role')->orderBy('number'),
        ]);

        return Inertia::render('devices/Show', [
            'device' => [
                ...$this->summarise($device),
                'serial' => $device->serial,
                'mgmt_url' => $device->mgmt_url,
                'notes' => $device->notes,
                'face' => $device->face,
                'device_model_id' => $device->device_model_id,
                'site_id' => $device->site_id,
                'rack_id' => $device->rack_id,
                'room' => $device->rack?->room
                    ? ['id' => $device->rack->room->id, 'name' => $device->rack->room->name]
                    : null,
                'ports' => $device->ports->values()->map(fn (Port $port) => [
                    'id' => $port->id,
                    'name' => $port->name,
                    'number' => $port->number,
                    'media' => $port->media,
                    'speed_mbps' => $port->speed_mbps,
                    'role' => $port->role,
                    'is_uplink' => $port->is_uplink,
                    'enabled' => $port->enabled,
                    'description' => $port->description,
                ])->all(),
            ],
            'models' => $this->models(),
            'racks' => $this->racks($device->site_id),
            'statuses' => Device::STATUSES,
            'faces' => Device::FACES,
            'can' => [
                'update' => request()->user()->can('update', $device),
                'delete' => request()->user()->can('delete', $device),
            ],
        ]);
    }

    public function store(DeviceRequest $request, CreatePortsFromModel $ports): RedirectResponse
    {
        $this->authorize('create', Device::class);

        $validated = $request->validated();
        $this->authorize('view', Site::findOrFail((int) $validated['site_id']));

        $device = Device::create($validated);
        $ports->handle($device);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Device created.')]);

        return to_route('devices.show', $device);
    }

    public function update(DeviceRequest $request, Device $device): RedirectResponse
    {
        $this->authorize('update', $device);

        $device->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Device updated.')]);

        return to_route('devices.show', $device);
    }

    public function destroy(Device $device): RedirectResponse
    {
        $this->authorize('delete', $device);

        $device->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Device deleted.')]);

        return to_route('devices.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function summarise(Device $device): array
    {
        return [
            'id' => $device->id,
            'name' => $device->name,
            'status' => $device->status,
            'position_u' => $device->position_u,
            'mgmt_ip' => $device->mgmt_ip,
            'ports_count' => $device->ports_count ?? $device->ports()->count(),
            'model' => [
                'id' => $device->deviceModel->id,
                'vendor' => $device->deviceModel->vendor,
                'model' => $device->deviceModel->model,
                'kind' => $device->deviceModel->kind,
                'u_height' => $device->deviceModel->u_height,
            ],
            'site' => [
                'id' => $device->site->id,
                'name' => $device->site->name,
                'code' => $device->site->code,
            ],
            'rack' => $device->rack
                ? ['id' => $device->rack->id, 'name' => $device->rack->name]
                : null,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function models(): array
    {
        return DeviceModel::orderBy('vendor')->orderBy('model')->get()
            ->map(fn (DeviceModel $model) => [
                'id' => $model->id,
                'vendor' => $model->vendor,
                'model' => $model->model,
                'kind' => $model->kind,
                'u_height' => $model->u_height,
            ])->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function racks(int $siteId): array
    {
        return Rack::query()
            ->whereIn('room_id', Room::where('site_id', $siteId)->select('id'))
            ->with('room:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (Rack $rack) => [
                'id' => $rack->id,
                'name' => $rack->name,
                'u_height' => $rack->u_height,
                'room' => ['id' => $rack->room->id, 'name' => $rack->room->name],
            ])->all();
    }
}
