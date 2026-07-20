<?php

namespace App\Http\Controllers;

use App\Models\Port;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PortController extends Controller
{
    /**
     * Ports are created with their device; what changes afterwards is the
     * description ("what is on this port"), the uplink flag and whether the
     * port is in use.
     */
    public function update(Request $request, Port $port): RedirectResponse
    {
        $this->authorize('update', $port->device);

        $port->update($request->validate([
            'description' => ['nullable', 'string', 'max:255'],
            'is_uplink' => ['boolean'],
            'enabled' => ['boolean'],
        ]));

        return back();
    }
}
