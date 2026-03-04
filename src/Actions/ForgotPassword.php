<?php

namespace Datalogix\Guardian\Actions;

use Datalogix\Guardian\Actions\Contracts\HasValidationRules;
use Datalogix\Guardian\Guardian;
use Illuminate\Auth\Events\PasswordResetLinkSent;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Support\Facades\Password;

class ForgotPassword implements HasValidationRules
{
    use Concerns\HasRateLimiter;

    public function __invoke(array $data = [])
    {
        return $this->throttleAction(fn () => Password::broker(Guardian::getPasswordBroker())->sendResetLink(
            $data,
            function (CanResetPassword $user, string $token) {
                if (Guardian::cannotAccess($user)) {
                    return;
                }

                ResetPassword::createUrlUsing(fn () => Guardian::getResetPasswordUrl($token, $user));

                $user->sendPasswordResetNotification($token);

                event(new PasswordResetLinkSent($user));
            },
        ), $data['email'] ?? null, Guardian::getForgotPasswordFeature()->getMaxAttempts());
    }

    public static function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
        ];
    }
}
