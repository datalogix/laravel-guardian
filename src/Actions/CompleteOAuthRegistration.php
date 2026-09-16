<?php

namespace Datalogix\Guardian\Actions;

use Datalogix\Guardian\Actions\Concerns\CreatesAuthenticatableUser;
use Datalogix\Guardian\Actions\Concerns\HasEmailVerifiedColumn;
use Datalogix\Guardian\Actions\Concerns\HasRateLimiter;
use Datalogix\Guardian\Actions\Contracts\HasValidationRules;
use Datalogix\Guardian\Enums\AuthFlowResult;
use Datalogix\Guardian\Exceptions\OAuthException;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Support\Auth\PostAuthenticationFlow;
use Datalogix\Guardian\Support\OAuth\OAuthIdentities;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CompleteOAuthRegistration implements HasValidationRules
{
    use CreatesAuthenticatableUser;
    use HasEmailVerifiedColumn;
    use HasRateLimiter;

    public function __construct(
        protected PostAuthenticationFlow $postAuthenticationFlow,
        protected OAuthIdentities $oauthIdentities,
    ) {
        //
    }

    public function __invoke(array $data = [], bool $remember = true): AuthFlowResult
    {
        $session = Guardian::getPendingOAuthRegistrationSession();

        if (! $session) {
            throw OAuthException::registrationNotPending();
        }

        return $this->throttleAction(
            function () use ($session, $data, $remember) {
                $modelClass = Guardian::authModelClass();
                $identifierKey = Guardian::getIdentifierKey();

                $attributes = [
                    'name' => $session['name'] ?: Str::headline($session['provider']).' User',
                    'email' => $session['email'],
                    'password' => Hash::make(Str::random(64)),
                    $identifierKey->value => $data['login'] ?? null,
                ];

                if ($this->hasEmailVerifiedColumn($modelClass) && ($session['email_verified'] ?? false)) {
                    $attributes['email_verified_at'] = now();
                }

                $user = Guardian::wrapInDatabaseTransaction(function () use ($modelClass, $attributes, $session) {
                    $user = $this->createAuthenticatableUser(
                        $modelClass,
                        $attributes,
                        OAuthException::cannotAccess(...),
                        OAuthException::unableToAuthenticate(...),
                        OAuthException::unableToAuthenticate(...),
                    );

                    try {
                        $this->oauthIdentities->link(
                            Guardian::getCurrentOrDefaultFortress(),
                            $user,
                            $session['provider'],
                            $session['provider_user_id'],
                            $session['email'],
                            $session['name'],
                            $session['avatar'],
                            $session['access_token'] ?? null,
                            $session['refresh_token'] ?? null,
                            isset($session['token_expires_at']) ? Carbon::parse($session['token_expires_at']) : null,
                        );
                    } catch (QueryException $exception) {
                        report($exception);

                        throw OAuthException::identityAlreadyLinked();
                    }

                    return $user;
                });

                $this->fireUserRegistered($user);

                Guardian::clearPendingOAuthRegistration();

                return $this->postAuthenticationFlow->handle($user, $remember);
            },
            fn (int $seconds) => throw OAuthException::rateLimited($seconds),
            $session['provider_user_id'],
            Guardian::getOAuthCompleteRegistrationFeature()->getMaxAttempts()
        );
    }

    public static function rules(): array
    {
        $identifierKey = Guardian::getIdentifierKey();

        return [
            'login' => $identifierKey->rules([Rule::unique(Guardian::authModelClass(), $identifierKey->value)]),
        ];
    }
}
