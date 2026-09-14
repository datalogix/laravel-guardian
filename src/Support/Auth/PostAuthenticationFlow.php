<?php

namespace Datalogix\Guardian\Support\Auth;

use Datalogix\Guardian\Enums\AuthFlowResult;
use Datalogix\Guardian\Exceptions\TwoFactorChallengeException;
use Datalogix\Guardian\Exceptions\TwoFactorDeliveryException;
use Datalogix\Guardian\Exceptions\TwoFactorSecretDecryptionException;
use Datalogix\Guardian\Guardian;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;

class PostAuthenticationFlow
{
    public function handle(Authenticatable $user, bool $remember = true): AuthFlowResult
    {
        try {
            $requiresChallenge = Guardian::requiresTwoFactorChallenge($user);
        } catch (TwoFactorSecretDecryptionException) {
            throw $this->secretUnreadableException();
        }

        if ($requiresChallenge) {
            try {
                Guardian::startTwoFactorChallenge($user, $remember);
            } catch (TwoFactorDeliveryException $exception) {
                Guardian::clearTwoFactorChallenge();

                throw ValidationException::withMessages([
                    'login' => Arr::flatten($exception->errors()),
                ]);
            } catch (TwoFactorChallengeException $exception) {
                throw ValidationException::withMessages([
                    'login' => Arr::flatten($exception->errors()),
                ]);
            }

            Session::regenerate();

            return AuthFlowResult::ChallengeRequired;
        }

        try {
            $requiresSetup = Guardian::requiresTwoFactorSetup($user);
        } catch (TwoFactorSecretDecryptionException) {
            throw $this->secretUnreadableException();
        }

        if ($requiresSetup) {
            Guardian::clearTwoFactorChallenge();
            Guardian::startPendingTwoFactorSetup($user, $remember);

            Session::regenerate();

            return AuthFlowResult::SetupRequired;
        }

        $this->finalize($user, $remember);

        return AuthFlowResult::Authenticated;
    }

    public function finalize(Authenticatable $user, bool $remember = true): void
    {
        Guardian::auth()->login($user, $remember);
        Guardian::clearTwoFactorChallenge();
        Guardian::clearPendingTwoFactorSetup();

        Session::regenerate();
    }

    protected function secretUnreadableException(): ValidationException
    {
        return ValidationException::withMessages([
            'login' => [__('Your two-factor authentication could not be verified. Please contact support.')],
        ]);
    }
}
