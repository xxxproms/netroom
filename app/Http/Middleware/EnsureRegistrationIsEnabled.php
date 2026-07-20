<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRegistrationIsEnabled
{
    /**
     * Block the self-service registration routes unless an administrator
     * opted in. NetRoom documents internal infrastructure, so accounts are
     * normally created from the admin area instead.
     *
     * Fortify's registration feature stays enabled so the routes (and their
     * generated front-end helpers) always exist; only access is gated here.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $isRegistration = in_array($request->route()?->getName(), ['register', 'register.store'], true);

        abort_if($isRegistration && ! config('netroom.allow_registration'), 403, __('Registration is disabled.'));

        return $next($request);
    }
}
