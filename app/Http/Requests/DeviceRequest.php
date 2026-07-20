<?php

namespace App\Http\Requests;

use App\Models\Device;
use App\Models\DeviceModel;
use App\Models\Rack;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeviceRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $device = $this->route('device');

        return [
            'device_model_id' => ['required', 'exists:device_models,id'],
            'site_id' => ['required', 'exists:sites,id'],
            'rack_id' => ['nullable', 'exists:racks,id'],
            'position_u' => ['nullable', 'integer', 'min:1', 'required_with:rack_id'],
            'face' => ['required', Rule::in(Device::FACES)],
            'name' => [
                'required', 'string', 'max:120',
                Rule::unique('devices')->where('site_id', $this->input('site_id'))->ignore($device),
            ],
            'serial' => ['nullable', 'string', 'max:120'],
            'mgmt_ip' => ['nullable', 'ip', Rule::unique('devices')->ignore($device)],
            'mgmt_url' => ['nullable', 'url', 'max:255'],
            'status' => ['required', Rule::in(Device::STATUSES)],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * A device may not hang off the end of its rack, nor overlap one that is
     * already mounted on the same face.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $rackId = $this->input('rack_id');
            $position = (int) $this->input('position_u');

            if (! $rackId || ! $position) {
                return;
            }

            $rack = Rack::find((int) $rackId);
            $model = DeviceModel::find((int) $this->input('device_model_id'));

            if (! $rack || ! $model) {
                return;
            }

            $top = $position + $model->u_height - 1;

            if ($top > $rack->u_height) {
                $validator->errors()->add('position_u', __('The device does not fit in the rack at that position.'));

                return;
            }

            $wanted = range($position, $top);

            $clash = Device::query()
                ->where('rack_id', $rack->id)
                ->where('face', $this->input('face'))
                ->when($this->route('device'), fn ($query, $device) => $query->whereKeyNot($device))
                ->with('deviceModel:id,u_height')
                ->get()
                ->first(fn (Device $mounted) => array_intersect($wanted, $mounted->occupiedUnits()) !== []);

            if ($clash) {
                $validator->errors()->add('position_u', __('Those units are taken by :device.', ['device' => $clash->name]));
            }
        });
    }
}
