<?php

namespace App\Http\Controllers;

use App\Http\Requests\CableRequest;
use App\Models\Cable;
use App\Models\Outlet;
use App\Models\Port;
use App\Models\Site;
use App\Support\SiteContext;
use App\Support\Terminations;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The cable journal: every run at a site, both ends spelled out.
 */
class CableController extends Controller
{
    public function __construct(private readonly SiteContext $context) {}

    public function index(): Response
    {
        $this->authorize('viewAny', Cable::class);

        // Both ends are morphs; each type pulls the labels its card needs.
        $ends = function (Relation $morph): void {
            if ($morph instanceof MorphTo) {
                $morph->morphWith([
                    Port::class => ['device.deviceModel'],
                    Outlet::class => ['workplace.room'],
                ]);
            }
        };

        $cables = $this->context->scope(Cable::query())
            ->with('site:id,name,code')
            ->with(['a' => $ends, 'b' => $ends])
            ->orderByDesc('id')
            ->get()
            ->map(fn (Cable $cable) => [
                ...Terminations::describeCable($cable),
                'site' => $cable->site->only(['id', 'name', 'code']),
                'a' => Terminations::describe($cable->a),
                'b' => Terminations::describe($cable->b),
            ]);

        return Inertia::render('cables/Index', [
            'cables' => $cables,
            'media' => Cable::MEDIA,
            'statuses' => Cable::STATUSES,
            'strands' => Cable::STRANDS,
            'can' => [
                'update' => request()->user()->can('create', Cable::class),
            ],
        ]);
    }

    public function store(CableRequest $request): RedirectResponse
    {
        $this->authorize('create', Cable::class);

        $a = $request->end('a');
        $b = $request->end('b');
        $siteId = $a ? $request->siteOf($a) : null;

        // A cable is filed at the site of its first end. The two ends may sit
        // at different sites — neighbouring complexes are joined by fibre —
        // so both sites have to be ones this user is allowed to work with.
        abort_if($siteId === null || $b === null, 422);
        $this->authorize('view', Site::findOrFail($siteId));
        $this->authorize('view', Site::findOrFail($request->siteOf($b)));

        Cable::create([...$request->validated(), 'site_id' => $siteId]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Cable created.')]);

        return back();
    }

    public function update(CableRequest $request, Cable $cable): RedirectResponse
    {
        $this->authorize('update', $cable);

        $cable->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Cable updated.')]);

        return back();
    }

    /**
     * Just the look of a cable — its label, length, colour and status — with no
     * say over where it is plugged. The patching view edits cords through here,
     * so it need not resend both ends of an uplink it only half knows.
     */
    public function appearance(Request $request, Cable $cable): RedirectResponse
    {
        $this->authorize('update', $cable);

        $cable->update($request->validate([
            'label' => ['nullable', 'string', 'max:60'],
            'length_cm' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'status' => ['required', Rule::in(Cable::STATUSES)],
        ]));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Cable updated.')]);

        return back();
    }

    public function destroy(Cable $cable): RedirectResponse
    {
        $this->authorize('delete', $cable);

        $cable->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Cable removed.')]);

        return back();
    }
}
