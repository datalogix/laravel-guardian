<?php

namespace Datalogix\Guardian\Http\Middleware;

use Datalogix\Guardian\Guardian;
use Illuminate\Auth\Middleware\Authenticate as BaseAuthenticate;

class Authenticate extends BaseAuthenticate
{
    protected function authenticate($request, array $guards): void
    {
        $auth = Guardian::auth();

        if (! $auth->check()) {
            $this->unauthenticated($request, $guards);

            return;
        }

        $this->auth->shouldUse(Guardian::getGuard());

        abort_if(Guardian::cannotAccess($auth->user()), 403);
    }

    protected function redirectTo($request): ?string
    {
        return Guardian::getLoginUrl();
    }
}
