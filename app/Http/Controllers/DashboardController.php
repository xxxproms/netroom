<?php

namespace App\Http\Controllers;

use App\Models\Cable;
use App\Models\Device;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Subnet;
use App\Models\User;
use App\Models\Vlan;
use App\Models\Workplace;
use App\Support\SiteContext;
use App\Support\SubnetUsage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

/**
 * The landing page: how much of the network is documented, what needs a look,
 * and what changed lately — all read through the site picker, so an engineer
 * on one complex sees only their own estate.
 */
class DashboardController extends Controller
{
    public function __construct(
        private readonly SiteContext $context,
        private readonly SubnetUsage $usage,
    ) {}

    public function __invoke(): Response
    {
        $domainIds = $this->reachableDomainIds();

        return Inertia::render('Dashboard', [
            'stats' => $this->stats($domainIds),
            'attention' => $this->attention($domainIds),
            'activity' => $this->recentActivity(),
        ]);
    }

    /**
     * The headline counts, each narrowed to what the user is looking at.
     *
     * @param  Collection<int, int>  $domainIds
     * @return array<string, int>
     */
    private function stats(Collection $domainIds): array
    {
        return [
            'sites' => $this->context->available()->count(),
            'devices' => $this->context->scope(Device::query())->count(),
            'rooms' => $this->context->scope(Room::query())->count(),
            'racks' => Rack::whereHas('room', fn (Builder $q) => $this->context->scope($q))->count(),
            'cables' => $this->context->scope(Cable::query())->count(),
            'workplaces' => $this->context->scope(Workplace::query())->count(),
            'vlans' => Vlan::whereIn('vlan_domain_id', $domainIds)->count(),
            'subnets' => Subnet::whereIn('vlan_domain_id', $domainIds)->count(),
        ];
    }

    /**
     * Things worth fixing: addresses claimed twice, and switches brought in
     * with no VLANs documented on any port.
     *
     * @param  Collection<int, int>  $domainIds
     * @return array<string, int>
     */
    private function attention(Collection $domainIds): array
    {
        $conflicts = Subnet::whereIn('vlan_domain_id', $domainIds)
            ->get()
            ->sum(fn (Subnet $subnet) => $this->usage->summarise($subnet)['conflicts']);

        $switchesWithoutVlans = $this->context->scope(Device::query())
            ->whereHas('deviceModel', fn (Builder $q) => $q->where('kind', 'switch'))
            ->whereDoesntHave('ports.vlans')
            ->count();

        return [
            'ipamConflicts' => (int) $conflicts,
            'switchesWithoutVlans' => $switchesWithoutVlans,
        ];
    }

    /**
     * The last handful of changes anyone made, newest first.
     *
     * @return array<int, array{id: int, event: string, model: string, subject: string|null, causer: string|null, at: string}>
     */
    private function recentActivity(): array
    {
        return Activity::with('causer:id,name')
            ->latest()
            ->take(8)
            ->get()
            ->map(fn (Activity $entry) => $this->activityRow($entry))
            ->all();
    }

    /**
     * @return array{id: int, event: string, model: string, subject: string|null, causer: string|null, at: string}
     */
    private function activityRow(Activity $entry): array
    {
        $causer = $entry->causer;

        return [
            'id' => (int) $entry->id,
            'event' => (string) $entry->description,
            'model' => (string) $entry->log_name,
            'subject' => $this->subjectLabel($entry),
            'causer' => $causer instanceof User ? $causer->name : null,
            'at' => $entry->created_at?->toIso8601String() ?? '',
        ];
    }

    /**
     * A readable name for the thing that changed, from the logged attributes so
     * it still reads after the row itself is gone.
     */
    private function subjectLabel(Activity $entry): ?string
    {
        $properties = $entry->properties;
        $attributes = $properties->get('attributes', []);

        foreach (['name', 'cidr', 'label', 'model', 'address_text'] as $key) {
            if (is_array($attributes) && ! empty($attributes[$key])) {
                return (string) $attributes[$key];
            }
        }

        $name = $entry->subject?->getAttribute('name');

        return is_string($name) ? $name : null;
    }

    /**
     * VLAN domains behind the sites the user can reach — VLANs and subnets hang
     * off domains, not sites.
     *
     * @return Collection<int, int>
     */
    private function reachableDomainIds(): Collection
    {
        return $this->context->available()->pluck('vlan_domain_id')->unique()->values();
    }
}
