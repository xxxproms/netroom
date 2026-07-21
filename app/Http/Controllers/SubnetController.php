<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubnetRequest;
use App\Models\IpAddress;
use App\Models\Subnet;
use App\Models\Vlan;
use App\Models\VlanDomain;
use App\Support\SiteContext;
use App\Support\SubnetUsage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * IPAM: the subnets of a VLAN plan and what lives on each address.
 */
class SubnetController extends Controller
{
    public function __construct(
        private readonly SiteContext $context,
        private readonly SubnetUsage $usage,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Subnet::class);

        $domains = $this->reachableDomains();
        $selected = $this->selectedDomain($request, $domains);

        $subnets = $selected
            ? Subnet::where('vlan_domain_id', $selected->id)
                ->with('vlan:id,vid,name')
                ->orderBy('network')
                ->get()
            : collect();

        return Inertia::render('ipam/Index', [
            'subnets' => $subnets->map(function (Subnet $subnet) {
                $summary = $this->usage->summarise($subnet);

                return [
                    'id' => $subnet->id,
                    'cidr' => $subnet->cidr,
                    'name' => $subnet->name,
                    'gateway' => $subnet->gateway,
                    'vlan' => $subnet->vlan?->only(['id', 'vid', 'name']),
                    'capacity' => $summary['capacity'],
                    'used' => $summary['used'],
                    'utilisation' => $summary['utilisation'],
                    'conflicts' => $summary['conflicts'],
                ];
            }),
            'domains' => $domains->map(fn (VlanDomain $domain) => $domain->only(['id', 'name']))->values(),
            'selectedDomainId' => $selected?->id,
            'vlans' => $selected
                ? Vlan::where('vlan_domain_id', $selected->id)->orderBy('vid')->get(['id', 'vid', 'name'])
                : collect(),
            'can' => [
                'manage' => $request->user()->can('create', Subnet::class),
            ],
        ]);
    }

    public function show(Subnet $subnet): Response
    {
        $this->authorize('view', $subnet);

        $subnet->load(['vlan:id,vid,name', 'vlanDomain:id,name', 'addresses.device:id,name']);
        $summary = $this->usage->summarise($subnet);

        return Inertia::render('ipam/Show', [
            'subnet' => [
                'id' => $subnet->id,
                'cidr' => $subnet->cidr,
                'name' => $subnet->name,
                'gateway' => $subnet->gateway,
                'notes' => $subnet->notes,
                'vlan_domain_id' => $subnet->vlan_domain_id,
                'vlan_id' => $subnet->vlan_id,
                'domain' => $subnet->vlanDomain->only(['id', 'name']),
                'vlan' => $subnet->vlan?->only(['id', 'vid', 'name']),
            ],
            'summary' => $summary,
            'nextFree' => $this->usage->nextFree($subnet),
            'vlans' => Vlan::where('vlan_domain_id', $subnet->vlan_domain_id)
                ->orderBy('vid')
                ->get(['id', 'vid', 'name']),
            'statuses' => IpAddress::STATUSES,
            'can' => [
                'manage' => request()->user()->can('update', $subnet),
            ],
        ]);
    }

    public function store(SubnetRequest $request): RedirectResponse
    {
        $this->authorize('create', Subnet::class);

        $this->authorize('view', VlanDomain::findOrFail($request->integer('vlan_domain_id')));

        $subnet = new Subnet($request->safe()->except(['cidr']));
        $subnet->applyCidr($request->string('cidr')->toString());
        $subnet->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Subnet created.')]);

        return to_route('subnets.show', $subnet);
    }

    public function update(SubnetRequest $request, Subnet $subnet): RedirectResponse
    {
        $this->authorize('update', $subnet);

        $subnet->fill($request->safe()->except(['cidr']));
        $subnet->applyCidr($request->string('cidr')->toString());
        $subnet->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Subnet updated.')]);

        return back();
    }

    public function destroy(Subnet $subnet): RedirectResponse
    {
        $this->authorize('delete', $subnet);

        $subnet->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Subnet deleted.')]);

        return to_route('subnets.index');
    }

    /**
     * @return Collection<int, VlanDomain>
     */
    private function reachableDomains(): Collection
    {
        $ids = $this->context->available()->pluck('vlan_domain_id')->unique();

        return VlanDomain::whereIn('id', $ids)->orderBy('name')->get();
    }

    /**
     * @param  Collection<int, VlanDomain>  $domains
     */
    private function selectedDomain(Request $request, Collection $domains): ?VlanDomain
    {
        $requested = $request->integer('domain') ?: $this->context->current()?->vlan_domain_id;

        return $domains->firstWhere('id', $requested) ?? $domains->first();
    }
}
