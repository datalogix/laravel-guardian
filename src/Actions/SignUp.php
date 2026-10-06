<?php

namespace Datalogix\Guardian\Actions;

use Datalogix\Guardian\Actions\Concerns\CreatesAuthenticatableUser;
use Datalogix\Guardian\Actions\Concerns\HasEmailVerifiedColumn;
use Datalogix\Guardian\Actions\Concerns\HasRateLimiter;
use Datalogix\Guardian\Actions\Concerns\RemapsLoginField;
use Datalogix\Guardian\Actions\Contracts\HasValidationRules;
use Datalogix\Guardian\Enums\AuthFlowResult;
use Datalogix\Guardian\Enums\IdentifierKey;
use Datalogix\Guardian\Exceptions\SignUpException;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Support\Auth\PostAuthenticationFlow;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class SignUp implements HasValidationRules
{
    use CreatesAuthenticatableUser;
    use HasEmailVerifiedColumn;
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
                // Only the form's fields: a model with $guarded = [] would accept anything.
                $data = Arr::only($data, array_keys(static::rules()));
                $attributes = $this->remapLoginField(Arr::except($data, ['password_confirmation', 'terms']));

                // Hashed here: the model may lack the "hashed" cast.
                if (is_string($attributes['password'] ?? null)) {
                    $attributes['password'] = Hash::make($attributes['password']);
                }

                $guardianAttributes = [];

                if (Guardian::getSignUpTermsUrl() !== null && $this->hasModelColumn($modelClass, 'terms_accepted_at')) {
                    $guardianAttributes['terms_accepted_at'] = now();
                }

                $user = $this->createAuthenticatableUser(
                    $modelClass,
                    $attributes,
                    SignUpException::cannotAccess(...),
                    SignUpException::emailAlreadyExists(...),
                    SignUpException::unableToRegister(...),
                    guardianAttributes: $guardianAttributes,
                );

                $this->fireUserRegistered($user);

                return $this->postAuthenticationFlow->handle($user, $remember);
            },
            fn (int $seconds) => throw SignUpException::rateLimited($seconds),
            (string) Guardian::getIdentifierKey()->normalize($data['login'] ?? ''),
            Guardian::getSignUpFeature()->getMaxAttempts()
        );
    }

    public static function rules(): array
    {
        $identifierKey = Guardian::getIdentifierKey();
        $modelClass = Guardian::authModelClass();

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'login' => $identifierKey->rules([$identifierKey->unique($modelClass)]),
            'password' => ['required', 'string', Password::default(), 'confirmed'],
            'password_confirmation' => ['required', 'string', Password::default()],
        ];

        if (Guardian::getSignUpTermsUrl() !== null) {
            $rules['terms'] = ['required', 'accepted'];
        }

        if ($identifierKey !== IdentifierKey::Email) {
            $rules += ['email' => ['required', 'string', 'email', 'max:255', IdentifierKey::Email->unique($modelClass)]];
        }

        return $rules;
    }
}
