<?php

namespace Datalogix\Guardian\Actions;

use Datalogix\Guardian\Guardian;
use Illuminate\Auth\Events\PasswordResetLinkSent;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Session;

class ForgotPassword
{
    public function __invoke(array $data = [])
    {
        $status = Password::broker(Guardian::getPasswordBroker())->sendResetLink(
            $data,
            function (CanResetPassword $user, string $token) {
                if (Guardian::cannotAccess($user)) {
                    return;
                }

                ResetPassword::createUrlUsing(fn () => Guardian::getResetPasswordUrl($token, $user));

                $user->sendPasswordResetNotification($token);

                event(new PasswordResetLinkSent($user));
            },
        );

        Session::flash('status', __($status));
    }

    public static function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
        ];
    }
}
