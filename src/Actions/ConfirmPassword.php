<?php

namespace Datalogix\Guardian\Actions;

use Datalogix\Guardian\Actions\Contracts\HasValidationRules;
use Datalogix\Guardian\Exceptions\PasswordConfirmationException;
use Datalogix\Guardian\Guardian;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rules\Password as PasswordRule;

class ConfirmPassword implements HasValidationRules
{
    use Concerns\HasRateLimiter;

    public function __invoke(array $data = []): void
    {
        $auth = Guardian::auth();
        $user = $auth->user();

        if (! $user) {
            throw PasswordConfirmationException::invalid();
        }

        $this->throttleAction(function () use ($data, $auth, $user) {
            $data['email'] = $user->email;

            if (! $auth->validate($data)) {
                throw PasswordConfirmationException::invalid();
            }

            Session::put('auth.password_confirmed_at', time());
        }, $auth->id(), Guardian::getPasswordConfirmationFeature()->getMaxAttempts());
    }

    public static function rules(): array
    {
        return [
            'password' => ['required', 'string', PasswordRule::default()],
        ];
    }
}
