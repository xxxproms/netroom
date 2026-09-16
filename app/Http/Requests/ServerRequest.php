<?php

namespace App\Http\Requests;

use App\Models\Device;
use App\Models\PortTemplate;
use App\Models\Rack;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A quick server built straight into a rack: the same placement rules as a
 * catalogue device, plus the height and port count that stand in for a model.
 */
class ServerRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'site_id' => ['required', 'exists:sites,id'],
            'rack_id' => ['required', 'exists:racks,id'],
            'position_u' => ['required', 'integer', 'min:1'],
            'face' => ['required', Rule::in(Device::FACES)],
            'name' => [
                'required', 'string', 'max:120',
                Rule::unique('devices')->where('site_id', $this->input('site_id')),
            ],
            'mgmt_ip' => ['nullable', 'ip', Rule::unique('devices')],
            'status' => ['required', Rule::in(Device::STATUSES)],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'u_height' => ['required', 'integer', 'min:1', 'max:10'],
            'port_count' => ['required', 'integer', 'min:1', 'max:200'],
            'port_media' => ['required', Rule::in(PortTemplate::MEDIA)],
        ];
    }

    /**
     * A server may not hang off the end of its rack, nor overlap one already
     * mounted on the same face. Its height comes from the form rather than a
     * saved model, so the check is spelled out here rather than shared with
     * DeviceRequest.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $rack = Rack::find((int) $this->input('rack_id'));
            $position = (int) $this->input('position_u');
            $height = (int) $this->input('u_height');

            if (! $rack || ! $position || ! $height) {
                return;
            }

            $top = $position + $height - 1;

            if ($top > $rack->u_height) {
                $validator->errors()->add('position_u', __('The device does not fit in the rack at that position.'));

                return;
            }

            $wanted = range($position, $top);

            $clash = Device::query()
                ->where('rack_id', $rack->id)
                ->where('face', $this->input('face'))
                ->with('deviceModel:id,u_height')
                ->get()
                ->first(fn (Device $mounted) => array_intersect($wanted, $mounted->occupiedUnits()) !== []);

            if ($clash) {
                $validator->errors()->add('position_u', __('Those units are taken by :device.', ['device' => $clash->name]));
            }
        });
    }
}
