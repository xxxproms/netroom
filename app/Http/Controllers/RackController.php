<?php

namespace App\Http\Controllers;

use App\Models\Rack;
use App\Models\Room;
use App\Support\SiteContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RackController extends Controller
{
    public function __construct(private readonly SiteContext $context) {}

    public function index(): Response
    {
        $this->authorize('viewAny', Rack::class);

        $racks = Rack::query()
            ->whereIn('room_id', $this->context->scope(Room::query())->select('rooms.id'))
            ->with('room.site:id,name,code')
            ->orderBy('sort')
            ->orderBy('name')
            ->get()
            ->map(fn (Rack $rack) => [
                'id' => $rack->id,
                'name' => $rack->name,
                'kind' => $rack->kind,
                'u_height' => $rack->u_height,
                'room' => ['id' => $rack->room->id, 'name' => $rack->room->name],
                'site' => ['id' => $rack->room->site->id, 'code' => $rack->room->site->code],
            ]);

        return Inertia::render('racks/Index', [
            'racks' => $racks,
            'kinds' => Rack::KINDS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Rack::class);

        $validated = $this->validated($request);

        $room = Room::findOrFail($validated['room_id']);
        $this->authorize('update', $room);

        Rack::create($validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Rack created.')]);

        return to_route('rooms.show', $room);
    }

    public function update(Request $request, Rack $rack): RedirectResponse
    {
        $this->authorize('update', $rack);

        $rack->update($this->validated($request, $rack));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Rack updated.')]);

        return back();
    }

    public function destroy(Rack $rack): RedirectResponse
    {
        $this->authorize('delete', $rack);

        $room = $rack->room;
        $rack->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Rack deleted.')]);

        return to_route('rooms.show', $room);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Rack $rack = null): array
    {
        return $request->validate([
            'room_id' => ['required', 'exists:rooms,id'],
            'name' => [
                'required', 'string', 'max:120',
                Rule::unique('racks')->where('room_id', $request->input('room_id'))->ignore($rack),
            ],
            'u_height' => ['required', 'integer', 'min:1', 'max:60'],
            'kind' => ['required', Rule::in(Rack::KINDS)],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
