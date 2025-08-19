<?php

namespace Datalogix\Guardian\Actions;

use Datalogix\Guardian\Guardian;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class ConfirmPassword
{
    public function __invoke(array $data = [])
    {
        $auth = Guardian::auth();
        $data['email'] = $auth->user()->email;

        if (! $auth->validate($data)) {
            throw ValidationException::withMessages(['password' => [__('auth.password')]]);
        }

        Session::put('auth.password_confirmed_at', time());

        return Guardian::redirect(intended: true);
    }

    public static function rules(): array
    {
        return [
            'password' => ['required', 'string', PasswordRule::default()],
        ];
    }
}
