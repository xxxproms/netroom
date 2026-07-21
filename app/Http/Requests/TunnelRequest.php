<?php

namespace App\Http\Requests;

use App\Models\Device;
use App\Models\Tunnel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TunnelRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'site_a_id' => ['required', 'integer', 'exists:sites,id'],
            'site_b_id' => ['required', 'integer', 'exists:sites,id', 'different:site_a_id'],
            'device_a_id' => ['nullable', 'integer', 'exists:devices,id'],
            'device_b_id' => ['nullable', 'integer', 'exists:devices,id'],
            'type' => ['required', Rule::in(Tunnel::TYPES)],
            'status' => ['required', Rule::in(Tunnel::STATUSES)],
            'label' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            // A terminator must actually stand at the site it terminates.
            foreach (['a', 'b'] as $side) {
                $deviceId = $this->input("device_{$side}_id");
                $siteId = $this->input("site_{$side}_id");

                if ($deviceId === null) {
                    continue;
                }

                $belongs = Device::where('id', $deviceId)
                    ->where('site_id', $siteId)
                    ->exists();

                if (! $belongs) {
                    $validator->errors()->add(
                        "device_{$side}_id",
                        __('This device is not at that site.'),
                    );
                }
            }
        });
    }
}
