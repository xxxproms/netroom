<?php

namespace App\Http\Middleware;

use App\Models\Site;
use App\Support\SiteContext;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'locale' => app()->getLocale(),
            'locales' => config('netroom.locales'),
            'allowRegistration' => (bool) config('netroom.allow_registration'),
            'siteContext' => fn () => $this->siteContext($request),
            'permissions' => fn () => $request->user()?->getAllPermissions()->pluck('name') ?? [],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * The site picker: which sites the user may reach and which one they are
     * looking at. Null means they are looking at all of them at once.
     *
     * @return array{current: array{id: int, name: string, code: string}|null, available: list<array{id: int, name: string, code: string}>}
     */
    private function siteContext(Request $request): array
    {
        if (! $request->user()) {
            return ['current' => null, 'available' => []];
        }

        $context = app(SiteContext::class);

        $present = fn (Site $site) => [
            'id' => $site->id,
            'name' => $site->name,
            'code' => $site->code,
        ];

        $current = $context->current();

        return [
            'current' => $current ? $present($current) : null,
            'available' => array_values($context->available()->map($present)->all()),
        ];
    }
}
