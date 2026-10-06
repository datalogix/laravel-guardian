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
 * Must run after Authenticate, which makes the request use the fortress guard.
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

        // Only enabling it signs other sessions out.
        if ($current !== null && ! hash_equals($stored, $current)) {
            $this->logout($request);
        }
    }

    /**
     * Also stored when this session changes it, so only the other sessions are signed out.
     */
    public static function storeTwoFactorState(Authenticatable $user): void
    {
        if (Guardian::hasAnyTwoFactorFeature()) {
            // Empty while disabled, so enabling it later is noticed.
            Session::put(static::twoFactorSessionKey(), static::twoFactorFingerprint($user) ?? '');
        }
    }

    protected static function twoFactorSessionKey(): string
    {
        return 'guardian_two_factor_'.Guardian::getGuard();
    }

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
     * Stored on sign-in: a password reset before the next request must still sign it out.
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
            // A guard without hashPasswordForCookie() keeps the hash as it is.
        }

        Session::put('password_hash_'.Guardian::getGuard(), $passwordHash);
    }

    protected function redirectTo(Request $request)
    {
        return Guardian::getLoginFeature()->getUrl();
    }
}
