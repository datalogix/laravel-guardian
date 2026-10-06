<?php

namespace Datalogix\Guardian\Http\Middleware;

use BadMethodCallException;
use Closure;
use Datalogix\Guardian\Exceptions\TwoFactorSecretDecryptionException;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Support\TwoFactor\TwoFactorUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession as BaseAuthenticateSession;
use Illuminate\Support\Facades\Session;

/**
 * Signs a session out once the password of its user changes, so resetting a
 * stolen password also signs out whoever used it, and likewise once two-factor
 * authentication is enabled. It runs after Authenticate, which makes the guard of
 * the fortress the one the request uses.
 */
class AuthenticateSession extends BaseAuthenticateSession
{
    public function handle($request, Closure $next)
    {
        $this->ensureTwoFactorStateIsUnchanged($request);

        return parent::handle($request, $next);
    }

    protected function ensureTwoFactorStateIsUnchanged(Request $request): void
    {
        $user = $request->user();

        if (! $request->hasSession() || ! $user || ! Guardian::hasAnyTwoFactorFeature()) {
            return;
        }

        $stored = $request->session()->get(static::twoFactorSessionKey());

        if (! is_string($stored)) {
            static::storeTwoFactorState($user);

            return;
        }

        $current = static::twoFactorFingerprint($user);

        // Only enabling it signs the other sessions out: disabling it takes the secret
        // away, which is not a reason to sign out whoever is left.
        if ($current !== null && ! hash_equals($stored, $current)) {
            $this->logout($request);
        }
    }

    /**
     * Stored when the user signs in and when the session itself changes the state,
     * so that it is only the other sessions that are signed out.
     */
    public static function storeTwoFactorState(Authenticatable $user): void
    {
        if (Guardian::hasAnyTwoFactorFeature()) {
            // Empty while it is disabled, so that enabling it later still tells.
            Session::put(static::twoFactorSessionKey(), static::twoFactorFingerprint($user) ?? '');
        }
    }

    protected static function twoFactorSessionKey(): string
    {
        return 'guardian_two_factor_'.Guardian::getGuard();
    }

    /**
     * Every enabling of two-factor authentication comes with a new secret; null
     * while it is disabled.
     */
    protected static function twoFactorFingerprint(Authenticatable $user): ?string
    {
        try {
            $secret = app(TwoFactorUser::class)->getTwoFactorSecret($user, Guardian::getCurrentOrDefaultFortress());
        } catch (TwoFactorSecretDecryptionException) {
            $secret = 'unreadable';
        }

        return $secret === null ? null : hash_hmac('sha256', $secret, (string) config('app.key'));
    }

    /**
     * Stored when the user signs in, not on the next request: a password reset in
     * between would otherwise be taken as the password of the session.
     */
    public static function storePasswordHash(Authenticatable $user): void
    {
        $passwordHash = $user->getAuthPassword();

        if (! $passwordHash) {
            return;
        }

        try {
            $passwordHash = Guardian::auth()->hashPasswordForCookie($passwordHash);
        } catch (BadMethodCallException) {
            //
        }

        Session::put('password_hash_'.Guardian::getGuard(), $passwordHash);
    }

    protected function redirectTo(Request $request)
    {
        return Guardian::getLoginFeature()->getUrl();
    }
}
