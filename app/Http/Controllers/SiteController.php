<?php

namespace App\Http\Controllers;

use App\Http\Requests\SiteRequest;
use App\Models\Site;
use App\Models\VlanDomain;
use App\Support\SiteContext;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SiteController extends Controller
{
    public function __construct(private readonly SiteContext $context) {}

    public function index(): Response
    {
        $this->authorize('viewAny', Site::class);

        $sites = $this->context->available()
            ->load('vlanDomain')
            ->loadCount('rooms')
            ->values()
            ->map(fn (Site $site) => [
                'id' => $site->id,
                'name' => $site->name,
                'code' => $site->code,
                'kind' => $site->kind,
                'city' => $site->city,
                'color' => $site->color,
                'rooms_count' => $site->rooms_count,
                'vlan_domain' => [
                    'id' => $site->vlanDomain->id,
                    'name' => $site->vlanDomain->name,
                ],
            ]);

        return Inertia::render('sites/Index', [
            'sites' => $sites->values(),
            'domains' => $this->domains(),
            'kinds' => Site::KINDS,
            'can' => [
                'create' => request()->user()->can('create', Site::class),
            ],
        ]);
    }

    public function store(SiteRequest $request): RedirectResponse
    {
        $this->authorize('create', Site::class);

        $site = Site::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Site created.')]);

        return to_route('sites.show', $site);
    }

    public function show(Site $site): Response
    {
        $this->authorize('view', $site);

        $site->load(['vlanDomain', 'rooms' => fn ($query) => $query->withCount('racks')->orderBy('name')]);

        return Inertia::render('sites/Show', [
            'site' => [
                'id' => $site->id,
                'name' => $site->name,
                'code' => $site->code,
                'kind' => $site->kind,
                'city' => $site->city,
                'address' => $site->address,
                'color' => $site->color,
                'notes' => $site->notes,
                'vlan_domain_id' => $site->vlan_domain_id,
                'vlan_domain' => [
                    'id' => $site->vlanDomain->id,
                    'name' => $site->vlanDomain->name,
                    'sites_count' => $site->vlanDomain->sites()->count(),
                ],
                'rooms' => $site->rooms->map(fn ($room) => [
                    'id' => $room->id,
                    'name' => $room->name,
                    'kind' => $room->kind,
                    'floor' => $room->floor,
                    'racks_count' => $room->racks_count,
                ]),
            ],
            'domains' => $this->domains(),
            'kinds' => Site::KINDS,
            'can' => [
                'update' => request()->user()->can('update', $site),
                'delete' => request()->user()->can('delete', $site),
            ],
        ]);
    }

    public function update(SiteRequest $request, Site $site): RedirectResponse
    {
        $this->authorize('update', $site);

        $site->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Site updated.')]);

        return to_route('sites.show', $site);
    }

    public function destroy(Site $site): RedirectResponse
    {
        $this->authorize('delete', $site);

        $site->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Site deleted.')]);

        return to_route('sites.index');
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function domains(): array
    {
        return VlanDomain::orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (VlanDomain $domain) => ['id' => $domain->id, 'name' => $domain->name])
            ->all();
    }
}
