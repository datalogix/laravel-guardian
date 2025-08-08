<?php

namespace Datalogix\Fortress\Http\Middleware;

use Closure;
use Datalogix\Fortress\Facades\Fortress;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated as BaseRedirectIfAuthenticated;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfAuthenticated extends BaseRedirectIfAuthenticated
{
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        return parent::handle($request, $next, Fortress::getAuthGuard());
    }

    protected function redirectTo(Request $request): ?string
    {
        return Fortress::getHomeUrl();
    }
}
