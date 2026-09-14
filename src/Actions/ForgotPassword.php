<?php

namespace Datalogix\Guardian\Actions;

use Datalogix\Guardian\Actions\Concerns\HasRateLimiter;
use Datalogix\Guardian\Actions\Concerns\RemapsLoginField;
use Datalogix\Guardian\Actions\Contracts\HasValidationRules;
use Datalogix\Guardian\Exceptions\ResetPasswordException;
use Datalogix\Guardian\Guardian;
use Illuminate\Auth\Events\PasswordResetLinkSent;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class ForgotPassword implements HasValidationRules
{
    use HasRateLimiter;
    use RemapsLoginField;

    public function __invoke(array $data = [])
    {
        $credentials = $this->remapLoginField($data);

        return $this->throttleAction(
            function () use ($credentials) {
                return Password::broker(Guardian::getPasswordBroker())->sendResetLink(
                    $credentials,
                    function (CanResetPassword|Authenticatable $user, string $token) {
                        if (Guardian::cannotAccess($user)) {
                            return;
                        }

                        ResetPassword::createUrlUsing(fn (mixed $notifiable, string $notificationToken) => Guardian::getResetPasswordUrl($notificationToken, $notifiable));
                        $user->sendPasswordResetNotification($token);

                        event(new PasswordResetLinkSent($user));
                    },
                );
            },
            fn (int $seconds) => throw ResetPasswordException::rateLimited($seconds),
            Str::lower($data['login'] ?? ''),
            Guardian::getForgotPasswordFeature()->getMaxAttempts()
        );
    }

    public static function rules(): array
    {
        return [
            'login' => Guardian::getIdentifierKey()->rules(),
        ];
    }
}
