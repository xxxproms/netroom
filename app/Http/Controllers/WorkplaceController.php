<?php

namespace App\Http\Controllers;

use App\Models\Cable;
use App\Models\Outlet;
use App\Models\Room;
use App\Models\Site;
use App\Models\Workplace;
use App\Support\SiteContext;
use App\Support\Terminations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class WorkplaceController extends Controller
{
    public function __construct(private readonly SiteContext $context) {}

    public function index(): Response
    {
        $this->authorize('viewAny', Workplace::class);

        $workplaces = $this->context->scope(Workplace::query())
            ->with(['site:id,name,code', 'room:id,name'])
            ->withCount('outlets')
            ->orderBy('name')
            ->get()
            ->map(fn (Workplace $workplace) => [
                'id' => $workplace->id,
                'name' => $workplace->name,
                'person' => $workplace->person,
                'floor' => $workplace->floor,
                'outlets_count' => $workplace->outlets_count,
                'room' => $workplace->room?->only(['id', 'name']),
                'site' => $workplace->site->only(['id', 'name', 'code']),
            ]);

        return Inertia::render('workplaces/Index', [
            'workplaces' => $workplaces,
            'sites' => $this->context->available()->map(
                fn (Site $site) => $site->only(['id', 'name'])
            )->values(),
            'rooms' => $this->context->scope(Room::query())
                ->orderBy('name')
                ->get(['id', 'name', 'site_id']),
            'outletMedia' => Outlet::MEDIA,
            'can' => [
                'create' => request()->user()->can('create', Workplace::class),
            ],
        ]);
    }

    public function show(Workplace $workplace): Response
    {
        $this->authorize('view', $workplace);

        $workplace->load([
            'site:id,name,code',
            'room:id,name',
            'outlets' => fn ($query) => $query->orderBy('label'),
        ]);

        return Inertia::render('workplaces/Show', [
            'workplace' => [
                'id' => $workplace->id,
                'name' => $workplace->name,
                'person' => $workplace->person,
                'floor' => $workplace->floor,
                'notes' => $workplace->notes,
                'site_id' => $workplace->site_id,
                'room_id' => $workplace->room_id,
                'site' => $workplace->site->only(['id', 'name', 'code']),
                'room' => $workplace->room?->only(['id', 'name']),
                'outlets' => $workplace->outlets->map(fn (Outlet $outlet) => [
                    'id' => $outlet->id,
                    'label' => $outlet->label,
                    'media' => $outlet->media,
                    'notes' => $outlet->notes,
                    // What the socket is patched to right now, if anything.
                    'link' => Terminations::link($outlet),
                ])->all(),
            ],
            'rooms' => Room::where('site_id', $workplace->site_id)
                ->orderBy('name')
                ->get(['id', 'name', 'site_id']),
            'outletMedia' => Outlet::MEDIA,
            'cable' => [
                'media' => Cable::MEDIA,
                'statuses' => Cable::STATUSES,
                'strands' => Cable::STRANDS,
            ],
            'can' => [
                'update' => request()->user()->can('update', $workplace),
                'delete' => request()->user()->can('delete', $workplace),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Workplace::class);

        $validated = $this->validated($request);

        $this->authorize('view', Site::findOrFail($validated['site_id']));

        $workplace = Workplace::create($validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Workplace created.')]);

        return to_route('workplaces.show', $workplace);
    }

    public function update(Request $request, Workplace $workplace): RedirectResponse
    {
        $this->authorize('update', $workplace);

        $workplace->update($this->validated($request, $workplace));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Workplace updated.')]);

        return back();
    }

    public function destroy(Workplace $workplace): RedirectResponse
    {
        $this->authorize('delete', $workplace);

        $workplace->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Workplace deleted.')]);

        return to_route('workplaces.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Workplace $workplace = null): array
    {
        return $request->validate([
            'site_id' => ['required', 'exists:sites,id'],
            'room_id' => ['nullable', 'exists:rooms,id'],
            'name' => [
                'required', 'string', 'max:120',
                Rule::unique('workplaces')->where('site_id', $request->input('site_id'))->ignore($workplace),
            ],
            'person' => ['nullable', 'string', 'max:120'],
            'floor' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
