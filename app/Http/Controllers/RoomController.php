<?php

namespace App\Http\Controllers;

use App\Models\Rack;
use App\Models\Room;
use App\Models\Site;
use App\Support\SiteContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RoomController extends Controller
{
    public function __construct(private readonly SiteContext $context) {}

    public function index(): Response
    {
        $this->authorize('viewAny', Room::class);

        $rooms = $this->context->scope(Room::query())
            ->with('site:id,name,code')
            ->withCount('racks')
            ->orderBy('name')
            ->get()
            ->map(fn (Room $room) => [
                'id' => $room->id,
                'name' => $room->name,
                'kind' => $room->kind,
                'floor' => $room->floor,
                'racks_count' => $room->racks_count,
                'site' => ['id' => $room->site->id, 'name' => $room->site->name, 'code' => $room->site->code],
            ]);

        return Inertia::render('rooms/Index', [
            'rooms' => $rooms,
            'sites' => $this->context->available()->map(fn (Site $site) => [
                'id' => $site->id,
                'name' => $site->name,
            ])->values(),
            'kinds' => Room::KINDS,
            'can' => [
                'create' => request()->user()->can('create', Room::class),
            ],
        ]);
    }

    public function show(Room $room): Response
    {
        $this->authorize('view', $room);

        $room->load(['site:id,name,code', 'racks' => fn ($query) => $query->orderBy('sort')->orderBy('name')]);

        return Inertia::render('rooms/Show', [
            'room' => [
                'id' => $room->id,
                'name' => $room->name,
                'kind' => $room->kind,
                'floor' => $room->floor,
                'notes' => $room->notes,
                'site_id' => $room->site_id,
                'site' => ['id' => $room->site->id, 'name' => $room->site->name, 'code' => $room->site->code],
                'racks' => $room->racks->map(fn ($rack) => [
                    'id' => $rack->id,
                    'name' => $rack->name,
                    'kind' => $rack->kind,
                    'u_height' => $rack->u_height,
                ]),
            ],
            'kinds' => Room::KINDS,
            'rackKinds' => Rack::KINDS,
            'can' => [
                'update' => request()->user()->can('update', $room),
                'delete' => request()->user()->can('delete', $room),
                'createRack' => request()->user()->can('create', Rack::class),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Room::class);

        $validated = $this->validated($request);

        $this->authorize('view', Site::findOrFail($validated['site_id']));

        $room = Room::create($validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Room created.')]);

        return to_route('rooms.show', $room);
    }

    public function update(Request $request, Room $room): RedirectResponse
    {
        $this->authorize('update', $room);

        $room->update($this->validated($request, $room));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Room updated.')]);

        return to_route('rooms.show', $room);
    }

    public function destroy(Room $room): RedirectResponse
    {
        $this->authorize('delete', $room);

        $room->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Room deleted.')]);

        return to_route('rooms.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Room $room = null): array
    {
        return $request->validate([
            'site_id' => ['required', 'exists:sites,id'],
            'name' => [
                'required', 'string', 'max:120',
                Rule::unique('rooms')->where('site_id', $request->input('site_id'))->ignore($room),
            ],
            'floor' => ['nullable', 'string', 'max:30'],
            'kind' => ['required', Rule::in(Room::KINDS)],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
