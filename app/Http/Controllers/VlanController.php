<?php

namespace App\Http\Controllers;

use App\Models\Vlan;
use App\Models\VlanDomain;
use App\Support\SiteContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class VlanController extends Controller
{
    public function __construct(private readonly SiteContext $context) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Vlan::class);

        $domains = $this->reachableDomains();
        $selected = $this->selectedDomain($request, $domains);

        $vlans = $selected
            ? Vlan::where('vlan_domain_id', $selected->id)->orderBy('vid')->get()
            : collect();

        return Inertia::render('vlans/Index', [
            'vlans' => $vlans->map(fn (Vlan $vlan) => [
                'id' => $vlan->id,
                'vid' => $vlan->vid,
                'name' => $vlan->name,
                'description' => $vlan->description,
                'color' => $vlan->color,
            ]),
            'domains' => $domains->map(fn (VlanDomain $domain) => [
                'id' => $domain->id,
                'name' => $domain->name,
            ])->values(),
            'selectedDomainId' => $selected?->id,
            'can' => [
                'manage' => $request->user()->can('create', Vlan::class),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Vlan::class);

        Vlan::create($this->validated($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('VLAN created.')]);

        return back();
    }

    public function update(Request $request, Vlan $vlan): RedirectResponse
    {
        $this->authorize('update', $vlan);

        $vlan->update($this->validated($request, $vlan));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('VLAN updated.')]);

        return back();
    }

    public function destroy(Vlan $vlan): RedirectResponse
    {
        $this->authorize('delete', $vlan);

        $vlan->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('VLAN deleted.')]);

        return back();
    }

    /**
     * Copy a plan into another domain, so a new site does not have to have its
     * VLANs typed in again. Existing VIDs in the target are left alone.
     */
    public function copy(Request $request): RedirectResponse
    {
        $this->authorize('create', Vlan::class);

        $validated = $request->validate([
            'from_domain_id' => ['required', 'exists:vlan_domains,id'],
            'to_domain_id' => ['required', 'different:from_domain_id', 'exists:vlan_domains,id'],
        ]);

        $existing = Vlan::where('vlan_domain_id', $validated['to_domain_id'])->pluck('vid')->all();

        $copied = Vlan::where('vlan_domain_id', $validated['from_domain_id'])
            ->whereNotIn('vid', $existing)
            ->get()
            ->map(fn (Vlan $vlan) => Vlan::create([
                'vlan_domain_id' => $validated['to_domain_id'],
                'vid' => $vlan->vid,
                'name' => $vlan->name,
                'description' => $vlan->description,
                'color' => $vlan->color,
            ]))
            ->count();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => trans_choice('Copied :count VLAN.|Copied :count VLANs.', $copied, ['count' => $copied]),
        ]);

        return back();
    }

    /**
     * @return Collection<int, VlanDomain>
     */
    private function reachableDomains()
    {
        $ids = $this->context->available()->pluck('vlan_domain_id')->unique();

        return VlanDomain::whereIn('id', $ids)->orderBy('name')->get();
    }

    /**
     * @param  Collection<int, VlanDomain>  $domains
     */
    private function selectedDomain(Request $request, $domains): ?VlanDomain
    {
        // Default to the domain of the site the user is currently looking at.
        $requested = $request->integer('domain') ?: $this->context->current()?->vlan_domain_id;

        return $domains->firstWhere('id', $requested) ?? $domains->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Vlan $vlan = null): array
    {
        return $request->validate([
            'vlan_domain_id' => ['required', 'exists:vlan_domains,id'],
            'vid' => [
                'required', 'integer', 'min:1', 'max:4094',
                Rule::unique('vlans')
                    ->where('vlan_domain_id', $request->input('vlan_domain_id'))
                    ->ignore($vlan),
            ],
            'name' => ['required', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);
    }
}
