<?php

namespace App\Http\Controllers;

use App\Actions\ApplyVlanMatrix;
use App\Http\Requests\VlanMatrixRequest;
use App\Models\Device;
use App\Models\Port;
use App\Models\Vlan;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The VLAN × port grid the department already thinks in, taken over from the
 * spreadsheet where membership was encoded as cell colours.
 */
class VlanMatrixController extends Controller
{
    public function show(Device $device): Response
    {
        $this->authorize('view', $device);

        $device->load(['deviceModel:id,vendor,model,kind', 'site:id,name,code,vlan_domain_id']);

        $ports = $device->ports()
            ->where('role', 'network')
            ->orderBy('number')
            ->get();

        $vlans = Vlan::where('vlan_domain_id', $device->site->vlan_domain_id)
            ->orderBy('vid')
            ->get();

        return Inertia::render('devices/Vlans', [
            'device' => [
                'id' => $device->id,
                'name' => $device->name,
                'model' => "{$device->deviceModel->vendor} {$device->deviceModel->model}",
                'site' => $device->site->only(['id', 'name', 'code']),
            ],
            'ports' => $ports->map(fn (Port $port) => [
                'id' => $port->id,
                'name' => $port->name,
                'number' => $port->number,
                'media' => $port->media,
                'is_uplink' => $port->is_uplink,
                'enabled' => $port->enabled,
                'description' => $port->description,
            ])->all(),
            'vlans' => $vlans->map(fn (Vlan $vlan) => [
                'id' => $vlan->id,
                'vid' => $vlan->vid,
                'name' => $vlan->name,
                'description' => $vlan->description,
                'color' => $vlan->color,
            ])->all(),
            'membership' => $this->membership($ports),
            'can' => [
                'update' => request()->user()->can('updateVlans', $device),
            ],
        ]);
    }

    public function update(VlanMatrixRequest $request, Device $device, ApplyVlanMatrix $apply): RedirectResponse
    {
        $this->authorize('updateVlans', $device);

        /** @var list<array{port_id: int, vlan_id: int, mode?: string|null}> $changes */
        $changes = $request->validated('changes');

        $applied = $apply->handle($changes);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => trans_choice('Saved :count change.|Saved :count changes.', $applied, ['count' => $applied]),
        ]);

        return back();
    }

    /**
     * Membership as the grid wants it: "port id → vlan id → tagged|untagged".
     *
     * @param  EloquentCollection<int, Port>  $ports
     * @return array<int, array<int, string>>
     */
    private function membership(EloquentCollection $ports): array
    {
        $ports->load('vlans');

        $membership = [];

        foreach ($ports as $port) {
            foreach ($port->vlans as $vlan) {
                $membership[$port->id][$vlan->id] = $vlan->pivot->mode;
            }
        }

        return $membership;
    }
}
