<?php

namespace App\Http\Requests;

use App\Models\Device;
use App\Models\Port;
use App\Models\Vlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class VlanMatrixRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'changes' => ['required', 'array', 'min:1'],
            'changes.*.port_id' => ['required', 'integer', 'exists:ports,id'],
            'changes.*.vlan_id' => ['required', 'integer', 'exists:vlans,id'],
            // A missing mode means "this VLAN is no longer on this port".
            'changes.*.mode' => ['nullable', 'in:tagged,untagged'],
        ];
    }

    /**
     * The matrix only ever addresses its own device's ports and the VLAN plan
     * of the site the device stands at — anything else is a forged request.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var Device $device */
            $device = $this->route('device');

            /** @var list<array{port_id: int, vlan_id: int, mode?: string|null}> $changes */
            $changes = $this->input('changes', []);

            $ports = Port::whereIn('id', array_column($changes, 'port_id'))
                ->where('device_id', $device->id)
                ->pluck('id')
                ->all();

            $vlans = Vlan::whereIn('id', array_column($changes, 'vlan_id'))
                ->where('vlan_domain_id', $device->site->vlan_domain_id)
                ->pluck('id')
                ->all();

            foreach ($changes as $index => $change) {
                if (! in_array($change['port_id'], $ports, true)) {
                    $validator->errors()->add("changes.{$index}.port_id", __('This port belongs to another device.'));
                }

                if (! in_array($change['vlan_id'], $vlans, true)) {
                    $validator->errors()->add("changes.{$index}.vlan_id", __('This VLAN is not in the site\'s plan.'));
                }
            }
        });
    }
}
