<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HandleLocale
{
    /**
     * Apply the locale a user picked in their profile, falling back to the
     * application default for guests and users who never chose one.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->user()?->locale;

        if ($locale && array_key_exists($locale, config('netroom.locales'))) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
