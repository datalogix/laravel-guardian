<?php

namespace Datalogix\Guardian\Support\TwoFactor;

use Datalogix\Guardian\Exceptions\UnsupportedAuthGuardException;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Http\Middleware\AuthenticateSession;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Str;

/**
 * Enabling two-factor authentication is what a user does who fears someone
 * else has their password, so whoever else is signed in is signed out.
 */
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
            //
        }

        // The other sessions are signed out on their next request; this one stays.
        if (Guardian::auth()->id() !== null && (string) Guardian::auth()->id() === (string) $user->getAuthIdentifier()) {
            AuthenticateSession::storeTwoFactorState($user);
        }
    }
}
