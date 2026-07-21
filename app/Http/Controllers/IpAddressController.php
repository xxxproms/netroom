<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\IpAddress;
use App\Models\Subnet;
use App\Support\Cidr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class IpAddressController extends Controller
{
    public function store(Request $request, Subnet $subnet): RedirectResponse
    {
        $this->authorize('update', $subnet);

        $data = $this->validated($request, $subnet);

        $address = new IpAddress($data);
        $address->subnet_id = $subnet->id;
        $address->applyAddress($data['address_text']);
        $address->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Address reserved.')]);

        return back();
    }

    public function update(Request $request, IpAddress $ipAddress): RedirectResponse
    {
        $this->authorize('update', $ipAddress);

        $data = $this->validated($request, $ipAddress->subnet, $ipAddress);

        $ipAddress->fill($data);
        $ipAddress->applyAddress($data['address_text']);
        $ipAddress->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Address updated.')]);

        return back();
    }

    public function destroy(IpAddress $ipAddress): RedirectResponse
    {
        $this->authorize('delete', $ipAddress);

        $ipAddress->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Address released.')]);

        return back();
    }

    /**
     * A reserved address has to fall inside the subnet it is reserved in, and a
     * device it names has to be one at the subnet's own VLAN plan.
     *
     * @return array<string, mixed>
     */
    private function validated(Request $request, Subnet $subnet, ?IpAddress $address = null): array
    {
        return $request->validate([
            'address_text' => [
                'required', 'string', 'max:45',
                Rule::unique('ip_addresses', 'address_text')
                    ->where('subnet_id', $subnet->id)
                    ->ignore($address),
                function (string $attribute, mixed $value, \Closure $fail) use ($subnet) {
                    $long = Cidr::toLong((string) $value);

                    if ($long === null || ! $subnet->range()->contains($long)) {
                        $fail(__('This address is outside the subnet.'));
                    }
                },
            ],
            'device_id' => [
                'nullable', 'integer', 'exists:devices,id',
                function (string $attribute, mixed $value, \Closure $fail) use ($subnet) {
                    $belongs = Device::where('id', $value)
                        ->whereHas('site', fn ($query) => $query->where('vlan_domain_id', $subnet->vlan_domain_id))
                        ->exists();

                    if (! $belongs) {
                        $fail(__('This device is not on this VLAN plan.'));
                    }
                },
            ],
            'hostname' => ['nullable', 'string', 'max:120'],
            'status' => ['required', Rule::in(IpAddress::STATUSES)],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
