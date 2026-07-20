<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\DeviceModel;
use App\Models\Rack;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RackElevationController extends Controller
{
    /**
     * The rack as it looks in the room: units from the top down, with each
     * device drawn at the unit it is mounted at.
     */
    public function show(Rack $rack): Response
    {
        $this->authorize('view', $rack);

        $rack->load('room.site:id,name,code');

        $devices = Device::where('rack_id', $rack->id)
            ->with('deviceModel:id,vendor,model,kind,u_height')
            ->orderByDesc('position_u')
            ->get()
            ->map(fn (Device $device) => [
                'id' => $device->id,
                'name' => $device->name,
                'face' => $device->face,
                'position_u' => $device->position_u,
                'status' => $device->status,
                'mgmt_ip' => $device->mgmt_ip,
                'model' => [
                    'vendor' => $device->deviceModel->vendor,
                    'model' => $device->deviceModel->model,
                    'kind' => $device->deviceModel->kind,
                    'u_height' => $device->deviceModel->u_height,
                ],
            ])->all();

        return Inertia::render('racks/Show', [
            'rack' => [
                'id' => $rack->id,
                'name' => $rack->name,
                'kind' => $rack->kind,
                'u_height' => $rack->u_height,
                'notes' => $rack->notes,
                'room' => ['id' => $rack->room->id, 'name' => $rack->room->name],
                'site' => [
                    'id' => $rack->room->site->id,
                    'name' => $rack->room->site->name,
                    'code' => $rack->room->site->code,
                ],
            ],
            'devices' => $devices,
            'models' => DeviceModel::orderBy('vendor')->orderBy('model')->get()
                ->map(fn (DeviceModel $model) => [
                    'id' => $model->id,
                    'vendor' => $model->vendor,
                    'model' => $model->model,
                    'kind' => $model->kind,
                    'u_height' => $model->u_height,
                ])->all(),
            'statuses' => Device::STATUSES,
            'faces' => Device::FACES,
            'can' => [
                'update' => request()->user()->can('update', $rack),
                'createDevice' => request()->user()->can('create', Device::class),
            ],
        ]);
    }

    /**
     * Move a device to another unit, which is what dragging it in the
     * elevation does. Validation of the new position lives in DeviceRequest,
     * so the same overlap rules are repeated here rather than duplicated.
     */
    public function move(Request $request, Rack $rack, Device $device): RedirectResponse
    {
        $this->authorize('update', $device);

        abort_unless($device->rack_id === $rack->id, 404);

        $validated = $request->validate([
            'position_u' => ['required', 'integer', 'min:1', 'max:'.$rack->u_height],
            'face' => ['required', Rule::in(Device::FACES)],
        ]);

        $top = $validated['position_u'] + $device->deviceModel->u_height - 1;

        if ($top > $rack->u_height) {
            return back()->withErrors([
                'position_u' => __('The device does not fit in the rack at that position.'),
            ]);
        }

        $wanted = range($validated['position_u'], $top);

        $clash = Device::where('rack_id', $rack->id)
            ->where('face', $validated['face'])
            ->whereKeyNot($device)
            ->with('deviceModel:id,u_height')
            ->get()
            ->first(fn (Device $mounted) => array_intersect($wanted, $mounted->occupiedUnits()) !== []);

        if ($clash) {
            return back()->withErrors([
                'position_u' => __('Those units are taken by :device.', ['device' => $clash->name]),
            ]);
        }

        $device->update($validated);

        return back();
    }
}
