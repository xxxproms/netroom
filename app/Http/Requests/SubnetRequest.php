<?php

namespace App\Http\Requests;

use App\Models\Subnet;
use App\Models\Vlan;
use App\Support\Cidr;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SubnetRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Subnet|null $subnet */
        $subnet = $this->route('subnet');

        return [
            'vlan_domain_id' => ['required', 'integer', 'exists:vlan_domains,id'],
            'vlan_id' => ['nullable', 'integer', 'exists:vlans,id'],
            'cidr' => [
                'required', 'string', 'max:43',
                Rule::unique('subnets', 'cidr')
                    ->where('vlan_domain_id', $this->input('vlan_domain_id'))
                    ->ignore($subnet),
            ],
            'name' => ['nullable', 'string', 'max:120'],
            'gateway' => ['nullable', 'string', 'max:45'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $range = Cidr::parse((string) $this->input('cidr'));

            if ($range === null) {
                $validator->errors()->add('cidr', __('This is not a valid IPv4 subnet, e.g. 10.40.0.0/24.'));

                return;
            }

            // A gateway that is not inside its own subnet is a typo worth catching.
            $gateway = $this->input('gateway');

            if ($gateway !== null && $gateway !== '') {
                $long = Cidr::toLong((string) $gateway);

                if ($long === null || ! $range->contains($long)) {
                    $validator->errors()->add('gateway', __('The gateway is not inside this subnet.'));
                }
            }

            // A VLAN, when named, must belong to the same plan as the subnet.
            $vlanId = $this->input('vlan_id');

            if ($vlanId !== null) {
                $belongs = Vlan::where('id', $vlanId)
                    ->where('vlan_domain_id', $this->input('vlan_domain_id'))
                    ->exists();

                if (! $belongs) {
                    $validator->errors()->add('vlan_id', __('This VLAN is not in the selected plan.'));
                }
            }
        });
    }
}
