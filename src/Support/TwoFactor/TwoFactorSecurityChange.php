<?php

namespace Datalogix\Guardian\Support\TwoFactor;

use Datalogix\Guardian\Exceptions\UnsupportedAuthGuardException;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Http\Middleware\AuthenticateSession;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Str;

class TwoFactorSecurityChange
{
    public function apply(object $user): void
    {
        if (! $user instanceof Authenticatable) {
            return;
        }

        // Ends every "remember me" cookie of the user.
        try {
            Guardian::authProvider()->updateRememberToken($user, Str::random(60));
        } catch (UnsupportedAuthGuardException) {
            // A guard without a provider has no remember token to rotate.
        }

        // The other sessions are signed out on their next request; this one stays.
        if (Guardian::auth()->id() !== null && (string) Guardian::auth()->id() === (string) $user->getAuthIdentifier()) {
            AuthenticateSession::storeTwoFactorState($user);
        }
    }
}
