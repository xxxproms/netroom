<?php

namespace App\Support;

use App\Models\Site;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;

/**
 * The site picker in the header. Every list, search and map in the panel is
 * read through it: either one chosen site, or all the sites a user may reach.
 */
class SiteContext
{
    private const SESSION_KEY = 'netroom.site';

    public function __construct(private readonly Request $request) {}

    /**
     * Sites the current user may work with, in display order.
     *
     * @return EloquentCollection<int, Site>
     */
    public function available(): EloquentCollection
    {
        $user = $this->request->user();

        if (! $user) {
            return new EloquentCollection;
        }

        return Site::query()
            ->when(! $user->has_all_sites, fn (Builder $query) => $query->whereIn(
                'id',
                $user->sites()->select('sites.id'),
            ))
            ->orderBy('name')
            ->get();
    }

    /**
     * The site currently selected, or null when the user is looking at all of
     * them. A stored choice the user lost access to is silently dropped.
     */
    public function current(): ?Site
    {
        $id = (int) $this->request->session()->get(self::SESSION_KEY);

        if (! $id) {
            return null;
        }

        $site = Site::find($id);

        if (! $site || ! $this->request->user()?->canAccessSite($site)) {
            $this->forget();

            return null;
        }

        return $site;
    }

    public function remember(?Site $site): void
    {
        if ($site) {
            $this->request->session()->put(self::SESSION_KEY, $site->getKey());

            return;
        }

        $this->forget();
    }

    public function forget(): void
    {
        $this->request->session()->forget(self::SESSION_KEY);
    }

    /**
     * Narrow a query to what the user should see: the selected site, or every
     * site they may reach when none is selected.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  string  $column  the query's own foreign key to sites
     * @return Builder<TModel>
     */
    public function scope(Builder $query, string $column = 'site_id'): Builder
    {
        if ($site = $this->current()) {
            return $query->where($column, $site->getKey());
        }

        $allowed = $this->request->user()?->accessibleSiteIds();

        return $allowed === null ? $query : $query->whereIn($column, $allowed);
    }
}
