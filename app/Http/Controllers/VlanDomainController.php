<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Models\VlanDomain;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class VlanDomainController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', VlanDomain::class);

        $domains = VlanDomain::withCount(['sites', 'vlans'])
            ->with(['sites:id,vlan_domain_id,name,code'])
            ->orderBy('name')
            ->get()
            ->map(fn (VlanDomain $domain) => [
                'id' => $domain->id,
                'name' => $domain->name,
                'notes' => $domain->notes,
                'sites_count' => $domain->sites_count,
                'vlans_count' => $domain->vlans_count,
                'sites' => $domain->sites->values()->map(fn (Site $site) => [
                    'id' => $site->id,
                    'name' => $site->name,
                    'code' => $site->code,
                ])->all(),
            ]);

        return Inertia::render('vlan-domains/Index', [
            'domains' => $domains,
            'can' => [
                'create' => request()->user()->can('create', VlanDomain::class),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', VlanDomain::class);

        VlanDomain::create($this->validated($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('VLAN domain created.')]);

        return to_route('vlan-domains.index');
    }

    public function update(Request $request, VlanDomain $vlanDomain): RedirectResponse
    {
        $this->authorize('update', $vlanDomain);

        $vlanDomain->update($this->validated($request, $vlanDomain));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('VLAN domain updated.')]);

        return to_route('vlan-domains.index');
    }

    public function destroy(VlanDomain $vlanDomain): RedirectResponse
    {
        $this->authorize('delete', $vlanDomain);

        // Sites keep their VLAN plan through the domain, so removing one that
        // is still in use would leave them without a plan.
        if ($vlanDomain->sites()->exists()) {
            return back()->withErrors([
                'name' => __('This VLAN domain still has sites in it.'),
            ]);
        }

        $vlanDomain->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('VLAN domain deleted.')]);

        return to_route('vlan-domains.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?VlanDomain $domain = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('vlan_domains')->ignore($domain)],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
