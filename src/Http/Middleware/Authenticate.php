<?php

namespace Datalogix\Fortress\Http\Middleware;

use Datalogix\Fortress\Facades\Fortress;
use Illuminate\Auth\Middleware\Authenticate as BaseAuthenticate;

class Authenticate extends BaseAuthenticate
{
    protected function authenticate($request, array $guards): void
    {
        $auth = Fortress::auth();

        if (! $auth->check()) {
            $this->unauthenticated($request, $guards);

            return;
        }

        $this->auth->shouldUse(Fortress::getAuthGuard());

        abort_if(Fortress::cannotAccess($auth->user()), 403);
    }

    protected function redirectTo($request): ?string
    {
        return Fortress::getLoginUrl();
    }
}
