<?php

namespace Datalogix\Guardian\Actions;

use Datalogix\Guardian\Actions\Concerns\CreatesAuthenticatableUser;
use Datalogix\Guardian\Actions\Concerns\HasRateLimiter;
use Datalogix\Guardian\Actions\Concerns\RemapsLoginField;
use Datalogix\Guardian\Actions\Contracts\HasValidationRules;
use Datalogix\Guardian\Enums\AuthFlowResult;
use Datalogix\Guardian\Enums\IdentifierKey;
use Datalogix\Guardian\Exceptions\SignUpException;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Support\Auth\PostAuthenticationFlow;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class SignUp implements HasValidationRules
{
    use CreatesAuthenticatableUser;
    use HasRateLimiter;
    use RemapsLoginField;

    public function __construct(
        protected PostAuthenticationFlow $postAuthenticationFlow,
    ) {}

    public function __invoke(array $data = [], bool $remember = false): AuthFlowResult
    {
        return $this->throttleAction(
            function () use ($data, $remember) {
                $modelClass = Guardian::authModelClass();
                $attributes = $this->remapLoginField(Arr::except($data, ['password_confirmation', 'terms']));

                $user = $this->createAuthenticatableUser(
                    $modelClass,
                    $attributes,
                    SignUpException::cannotAccess(...),
                    SignUpException::emailAlreadyExists(...),
                    SignUpException::unableToRegister(...),
                );

                $this->fireUserRegistered($user);

                return $this->postAuthenticationFlow->handle($user, $remember);
            },
            fn (int $seconds) => throw SignUpException::rateLimited($seconds),
            Str::lower($data['login'] ?? ''),
            Guardian::getSignUpFeature()->getMaxAttempts()
        );
    }

    public static function rules(): array
    {
        $identifierKey = Guardian::getIdentifierKey();
        $modelClass = Guardian::authModelClass();

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'login' => $identifierKey->rules([Rule::unique($modelClass, $identifierKey->value)]),
            'password' => ['required', 'string', Password::default(), 'confirmed'],
            'password_confirmation' => ['required', 'string', Password::default()],
            'terms' => ['required', 'accepted'],
        ];

        if ($identifierKey !== IdentifierKey::Email) {
            $rules += ['email' => ['required', 'string', 'email', 'max:255', Rule::unique($modelClass, 'email')]];
        }

        return $rules;
    }
}
