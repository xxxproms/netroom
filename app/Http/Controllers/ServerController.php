<?php

namespace App\Http\Controllers;

use App\Actions\CreateQuickServer;
use App\Http\Requests\ServerRequest;
use App\Models\Device;
use App\Models\Site;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class ServerController extends Controller
{
    public function store(ServerRequest $request, CreateQuickServer $servers): RedirectResponse
    {
        $this->authorize('create', Device::class);

        $validated = $request->validated();
        $this->authorize('view', Site::findOrFail((int) $validated['site_id']));

        $device = $servers->handle($validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Server added.')]);

        return to_route('racks.show', $device->rack_id);
    }
}
