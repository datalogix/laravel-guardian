<?php

namespace Datalogix\Guardian\Actions;

use Datalogix\Guardian\Actions\Concerns\HasRateLimiter;
use Datalogix\Guardian\Actions\Concerns\RemapsLoginField;
use Datalogix\Guardian\Actions\Contracts\HasValidationRules;
use Datalogix\Guardian\Exceptions\ResetPasswordException;
use Datalogix\Guardian\Guardian;
use Illuminate\Auth\Events\PasswordResetLinkSent;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Support\Timebox;

class ForgotPassword implements HasValidationRules
{
    use HasRateLimiter;
    use RemapsLoginField;

    /**
     * What the visitor is told, whether or not an account exists for the login.
     */
    public const GENERIC_STATUS = 'If an account exists for that login, we have emailed a password reset link.';

    /**
     * An IP may ask for as many links as this many times the limit of a single login.
     */
    public const IP_ATTEMPTS_MULTIPLIER = 10;

    public function __invoke(array $data = [])
    {
        $credentials = $this->remapLoginField($data);
        $maxAttempts = Guardian::getForgotPasswordFeature()->getMaxAttempts();
        $onLockout = fn (int $seconds) => throw ResetPasswordException::rateLimited($seconds);

        // The limit of a login alone does not stop someone from trying a different
        // login on every request, so the IP is limited as well.
        return $this->throttleAction(
            fn () => $this->throttleAction(
                fn () => $this->sendResetLink($credentials),
                $onLockout,
                Str::lower($data['login'] ?? ''),
                $maxAttempts,
            ),
            $onLockout,
            maxAttempts: is_int($maxAttempts) ? $maxAttempts * self::IP_ATTEMPTS_MULTIPLIER : $maxAttempts,
        );
    }

    protected function sendResetLink(array $credentials): string
    {
        if (Guardian::passwordResetRevealsAccounts()) {
            return $this->requestResetLink($credentials);
        }

        // Only an account that exists costs the time to create the token and to
        // send the message, so every answer takes the same time.
        return app(Timebox::class)->call(function () use ($credentials) {
            $this->requestResetLink($credentials);

            return self::GENERIC_STATUS;
        }, config('auth.timebox_duration', 200000));
    }

    protected function requestResetLink(array $credentials): string
    {
        return Password::broker(Guardian::getPasswordBroker())->sendResetLink(
            $credentials,
            function (CanResetPassword|Authenticatable $user, string $token) {
                if (Guardian::cannotAccess($user)) {
                    return;
                }

                $user->sendPasswordResetNotification($token);

                event(new PasswordResetLinkSent($user));
            },
        );
    }

    public static function rules(): array
    {
        return [
            'login' => Guardian::getIdentifierKey()->rules(),
        ];
    }
}
