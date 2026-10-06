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
            // unauthenticated() always throws (declared @return never on the
            // base Laravel middleware), so execution never continues past it.
            $this->unauthenticated($request, $guards);
        }

        $this->auth->shouldUse(Guardian::getGuard());

        $user = $auth->user();

        if (Guardian::cannotAccess($user)) {
            abort(403);
        }
    }

    protected function redirectTo($request): ?string
    {
        return Guardian::getLoginFeature()->getUrl();
    }
}
