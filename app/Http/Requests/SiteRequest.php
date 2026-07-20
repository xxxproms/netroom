<?php

namespace App\Http\Requests;

use App\Models\Site;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SiteRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $site = $this->route('site');

        return [
            'vlan_domain_id' => ['required', 'exists:vlan_domains,id'],
            'name' => ['required', 'string', 'max:120'],
            'code' => [
                'required', 'string', 'max:12', 'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('sites', 'code')->ignore($site),
            ],
            'kind' => ['required', Rule::in(Site::KINDS)],
            'city' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'vlan_domain_id' => __('VLAN domain'),
            'code' => __('code'),
        ];
    }
}
