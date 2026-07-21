<?php

namespace App\Http\Controllers;

use App\Http\Requests\TunnelRequest;
use App\Models\Site;
use App\Models\Tunnel;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class TunnelController extends Controller
{
    public function store(TunnelRequest $request): RedirectResponse
    {
        $this->authorize('create', Tunnel::class);

        // A tunnel joins two sites, so the user must be allowed at both ends.
        $this->authorize('view', Site::findOrFail($request->integer('site_a_id')));
        $this->authorize('view', Site::findOrFail($request->integer('site_b_id')));

        Tunnel::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tunnel created.')]);

        return back();
    }

    public function update(TunnelRequest $request, Tunnel $tunnel): RedirectResponse
    {
        $this->authorize('update', $tunnel);

        $tunnel->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tunnel updated.')]);

        return back();
    }

    public function destroy(Tunnel $tunnel): RedirectResponse
    {
        $this->authorize('delete', $tunnel);

        $tunnel->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tunnel removed.')]);

        return back();
    }
}
