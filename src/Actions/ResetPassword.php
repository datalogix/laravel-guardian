<?php

namespace Datalogix\Guardian\Actions;

use Datalogix\Guardian\Guardian;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class ResetPassword
{
    public function __invoke(array $data = [])
    {
        $hasPanelAccess = true;

        $status = Password::broker(Guardian::getPasswordBroker())->reset(
            $data,
            function (CanResetPassword|Model|Authenticatable $user, string $password) use (&$hasPanelAccess) {
                if (Guardian::cannotAccess($user)) {
                    $hasPanelAccess = false;

                    return;
                }

                $user->forceFill(['password' => Hash::make($password)]);
                $user->setRememberToken(Str::random(60));
                $user->save();

                event(new PasswordReset($user));
            }
        );

        if ($hasPanelAccess === false) {
            $status = Password::INVALID_USER;
        }

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => [__($status)]]);
        }

        Session::flash('status', __($status));

        return app(Guardian::getResetPasswordResponse());
    }

    public static function rules(): array
    {
        return [
            'token' => ['required'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', PasswordRule::default(), 'confirmed'],
        ];
    }
}
