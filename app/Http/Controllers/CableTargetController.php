<?php

namespace App\Http\Controllers;

use App\Models\Cable;
use App\Models\Device;
use App\Models\Outlet;
use App\Models\Port;
use App\Models\Workplace;
use App\Support\SiteContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Feeds the "what is the other end?" picker. A switch can have fifty ports and
 * a site a few hundred, so the dialog asks for one list at a time instead of
 * every port at the site being shipped with the page.
 */
class CableTargetController extends Controller
{
    public function __construct(private readonly SiteContext $context) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Cable::class);

        $validated = $request->validate([
            'scope' => ['required', 'in:devices,workplaces,ports,outlets'],
            'site' => ['nullable', 'integer'],
            'device' => ['nullable', 'integer'],
            'workplace' => ['nullable', 'integer'],
        ]);

        return response()->json(match ((string) $validated['scope']) {
            'devices' => $this->devices((int) ($validated['site'] ?? 0)),
            'workplaces' => $this->workplaces((int) ($validated['site'] ?? 0)),
            'ports' => $this->ports((int) ($validated['device'] ?? 0)),
            default => $this->outlets((int) ($validated['workplace'] ?? 0)),
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function devices(int $siteId): array
    {
        return Device::where('site_id', $siteId)
            ->with('deviceModel:id,kind')
            ->orderBy('name')
            ->get()
            ->filter(fn (Device $device) => request()->user()->can('view', $device))
            ->map(fn (Device $device) => [
                'id' => $device->id,
                'name' => $device->name,
                'kind' => $device->deviceModel->kind,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function workplaces(int $siteId): array
    {
        return $this->context->scope(Workplace::where('site_id', $siteId))
            ->orderBy('name')
            ->get()
            ->map(fn (Workplace $workplace) => [
                'id' => $workplace->id,
                'name' => $workplace->name,
                'person' => $workplace->person,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function ports(int $deviceId): array
    {
        $device = Device::findOrFail($deviceId);

        $this->authorize('view', $device);

        return $device->ports()
            ->with(['cableAsA:id,a_id,a_type', 'cableAsB:id,b_id,b_type'])
            ->orderBy('role')
            ->orderBy('number')
            ->get()
            ->map(fn (Port $port) => [
                'id' => $port->id,
                'name' => $port->name,
                'role' => $port->role,
                'media' => $port->media,
                'description' => $port->description,
                // A port already holding a cable cannot take a second one.
                'taken' => $port->cable() !== null,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function outlets(int $workplaceId): array
    {
        $workplace = Workplace::findOrFail($workplaceId);

        $this->authorize('view', $workplace);

        return $workplace->outlets()
            ->with(['cableAsA:id,a_id,a_type', 'cableAsB:id,b_id,b_type'])
            ->orderBy('label')
            ->get()
            ->map(fn (Outlet $outlet) => [
                'id' => $outlet->id,
                'label' => $outlet->label,
                'media' => $outlet->media,
                'taken' => $outlet->cable() !== null,
            ])
            ->values()
            ->all();
    }
}
