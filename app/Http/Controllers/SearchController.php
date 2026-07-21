<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\Room;
use App\Models\Site;
use App\Models\Subnet;
use App\Models\Vlan;
use App\Models\Workplace;
use App\Support\SiteContext;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * The header search box: one query run across the estate the user can reach,
 * grouped by kind so a switch, a desk and a subnet can be found from one place.
 * Read-only, so it needs nothing more than the view permission every user has.
 */
class SearchController extends Controller
{
    /** How many hits to show per group before it is worth narrowing the query. */
    private const PER_GROUP = 6;

    public function __construct(private readonly SiteContext $context) {}

    public function __invoke(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));

        if (mb_strlen($term) < 2) {
            return response()->json(['groups' => []]);
        }

        $like = '%'.$this->escape($term).'%';
        $domainIds = $this->context->available()->pluck('vlan_domain_id')->unique()->values();

        $groups = [
            $this->devices($like),
            $this->workplaces($like),
            $this->sites($like),
            $this->rooms($like),
            $this->subnets($like, $domainIds),
            $this->vlans($like, $domainIds),
        ];

        return response()->json([
            'groups' => array_values(array_filter($groups, fn (array $group) => $group['items'] !== [])),
        ]);
    }

    /**
     * @return array{key: string, items: array<int, array{title: string, subtitle: string|null, url: string}>}
     */
    private function devices(string $like): array
    {
        $items = $this->context->scope(Device::query())
            ->where(fn (Builder $q) => $q
                ->where('name', 'like', $like)
                ->orWhere('mgmt_ip', 'like', $like))
            ->orderBy('name')
            ->limit(self::PER_GROUP)
            ->get(['id', 'name', 'mgmt_ip'])
            ->map(fn (Device $device) => [
                'title' => $device->name,
                'subtitle' => $device->mgmt_ip,
                'url' => route('devices.show', $device),
            ])
            ->all();

        return ['key' => 'devices', 'items' => $items];
    }

    /**
     * @return array{key: string, items: array<int, array{title: string, subtitle: string|null, url: string}>}
     */
    private function workplaces(string $like): array
    {
        $items = $this->context->scope(Workplace::query())
            ->where(fn (Builder $q) => $q
                ->where('name', 'like', $like)
                ->orWhere('person', 'like', $like))
            ->orderBy('name')
            ->limit(self::PER_GROUP)
            ->get(['id', 'name', 'person'])
            ->map(fn (Workplace $workplace) => [
                'title' => $workplace->name,
                'subtitle' => $workplace->person,
                'url' => route('workplaces.show', $workplace),
            ])
            ->all();

        return ['key' => 'workplaces', 'items' => $items];
    }

    /**
     * @return array{key: string, items: array<int, array{title: string, subtitle: string|null, url: string}>}
     */
    private function sites(string $like): array
    {
        $reachable = $this->context->available()->pluck('id');

        $items = Site::whereIn('id', $reachable)
            ->where(fn (Builder $q) => $q
                ->where('name', 'like', $like)
                ->orWhere('code', 'like', $like))
            ->orderBy('name')
            ->limit(self::PER_GROUP)
            ->get(['id', 'name', 'code'])
            ->map(fn (Site $site) => [
                'title' => $site->name,
                'subtitle' => $site->code,
                'url' => route('sites.show', $site),
            ])
            ->all();

        return ['key' => 'sites', 'items' => $items];
    }

    /**
     * @return array{key: string, items: array<int, array{title: string, subtitle: string|null, url: string}>}
     */
    private function rooms(string $like): array
    {
        $items = $this->context->scope(Room::query())
            ->where('name', 'like', $like)
            ->orderBy('name')
            ->limit(self::PER_GROUP)
            ->get(['id', 'name', 'floor'])
            ->map(fn (Room $room) => [
                'title' => $room->name,
                'subtitle' => $room->floor,
                'url' => route('rooms.show', $room),
            ])
            ->all();

        return ['key' => 'rooms', 'items' => $items];
    }

    /**
     * @param  Collection<int, int>  $domainIds
     * @return array{key: string, items: array<int, array{title: string, subtitle: string|null, url: string}>}
     */
    private function subnets(string $like, Collection $domainIds): array
    {
        $items = Subnet::whereIn('vlan_domain_id', $domainIds)
            ->where(fn (Builder $q) => $q
                ->where('cidr', 'like', $like)
                ->orWhere('name', 'like', $like))
            ->orderBy('network')
            ->limit(self::PER_GROUP)
            ->get(['id', 'cidr', 'name'])
            ->map(fn (Subnet $subnet) => [
                'title' => $subnet->cidr,
                'subtitle' => $subnet->name,
                'url' => route('subnets.show', $subnet),
            ])
            ->all();

        return ['key' => 'subnets', 'items' => $items];
    }

    /**
     * @param  Collection<int, int>  $domainIds
     * @return array{key: string, items: array<int, array{title: string, subtitle: string|null, url: string}>}
     */
    private function vlans(string $like, Collection $domainIds): array
    {
        $items = Vlan::whereIn('vlan_domain_id', $domainIds)
            ->where(fn (Builder $q) => $q
                ->where('name', 'like', $like)
                ->orWhere('vid', 'like', $like))
            ->orderBy('vid')
            ->limit(self::PER_GROUP)
            ->get(['id', 'vid', 'name'])
            ->map(fn (Vlan $vlan) => [
                'title' => "VLAN {$vlan->vid}",
                'subtitle' => $vlan->name,
                'url' => route('vlans.index'),
            ])
            ->all();

        return ['key' => 'vlans', 'items' => $items];
    }

    /**
     * Treat the user's text as literal: escape the LIKE wildcards so a stray
     * "%" or "_" cannot turn into a match-everything query.
     */
    private function escape(string $term): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);
    }
}
