<?php

namespace Datalogix\Guardian\Http\Middleware;

use Closure;
use Datalogix\Guardian\Guardian;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorSetupAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Guardian::isAuthenticated()) {
            $user = Guardian::user();

            if ($user instanceof Model && Guardian::cannotAccess($user)) {
                abort(403);
            }

            return $next($request);
        }

        if (Guardian::hasPendingTwoFactorSetup()) {
            $pendingUser = Guardian::getPendingTwoFactorSetupUser();

            if (! $pendingUser instanceof Model) {
                Guardian::clearPendingTwoFactorSetup();

                return redirect()->guest(Guardian::getLoginFeature()->getUrl());
            }

            if (Guardian::cannotAccess($pendingUser)) {
                abort(403);
            }

            return $next($request);
        }

        return redirect()->guest(Guardian::getLoginFeature()->getUrl());
    }
}
