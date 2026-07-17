<?php

namespace Datalogix\Guardian\Actions;

use Datalogix\Guardian\Actions\Contracts\HasValidationRules;
use Datalogix\Guardian\Enums\IdentifierKey;
use Datalogix\Guardian\Guardian;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class SignUp implements HasValidationRules
{
    use Concerns\HasRateLimiter;

    public function __invoke(array $data = [])
    {
        return $this->throttleAction(function () use ($data) {
            $modelClass = Guardian::authModelClass();
            $user = Guardian::wrapInDatabaseTransaction(fn () => $modelClass::create($data));

            event(new Registered($user));

            app(SendEmailVerificationNotification::class)($user);

            Guardian::auth()->login($user);

            Session::regenerate();
        }, $data['email'] ?? null, Guardian::getSignUpFeature()->getMaxAttempts());
    }

    public static function rules(): array
    {
        $modelClass = Guardian::authModelClass();

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique($modelClass)],
            'password' => ['required', 'string', Password::default(), 'confirmed'],
            'password_confirmation' => ['required', 'string', Password::default()],
            'terms' => ['required', 'accepted'],
        ];

        if (Guardian::getIdentifierKey() !== IdentifierKey::Email) {
            $rules += ['username' => ['required', 'string', 'min:5', 'max:20', 'lowercase', 'alpha_num', Rule::unique($modelClass)]];
        }

        return $rules;
    }
}
