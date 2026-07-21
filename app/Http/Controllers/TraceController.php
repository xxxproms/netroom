<?php

namespace App\Http\Controllers;

use App\Actions\TraceCable;
use App\Models\Outlet;
use App\Models\Port;
use Illuminate\Http\JsonResponse;

/**
 * "Where does this port actually go?" — asked from a port row or a socket.
 */
class TraceController extends Controller
{
    public function port(Port $port, TraceCable $trace): JsonResponse
    {
        $this->authorize('view', $port->device);

        return response()->json(['path' => $trace->handle($port)]);
    }

    public function outlet(Outlet $outlet, TraceCable $trace): JsonResponse
    {
        $this->authorize('view', $outlet);

        return response()->json(['path' => $trace->handle($outlet)]);
    }
}
