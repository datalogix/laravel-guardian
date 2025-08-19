<?php

namespace Datalogix\Guardian\Http\Middleware;

use Closure;
use Datalogix\Guardian\Guardian;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated as BaseRedirectIfAuthenticated;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfAuthenticated extends BaseRedirectIfAuthenticated
{
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        return parent::handle($request, $next, Guardian::getGuard());
    }

    protected function redirectTo(Request $request): ?string
    {
        return Guardian::getHomeUrl();
    }
}
