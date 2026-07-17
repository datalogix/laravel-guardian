<?php

namespace Datalogix\Guardian\Support\Auth;

use Datalogix\Guardian\Enums\AuthFlowResult;
use Datalogix\Guardian\Guardian;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Session;

class PostAuthenticationFlow
{
    public function handle(Authenticatable $user, bool $remember = true): AuthFlowResult
    {
        if ($user instanceof Model && Guardian::requiresTwoFactorChallenge($user)) {
            Guardian::startTwoFactorChallenge($user, $remember);

            return AuthFlowResult::ChallengeRequired;
        }

        if ($user instanceof Model && Guardian::requiresTwoFactorSetup($user)) {
            Guardian::clearTwoFactorChallenge();
            Guardian::startPendingTwoFactorSetup($user, $remember);

            Session::regenerate();

            return AuthFlowResult::SetupRequired;
        }

        Guardian::auth()->login($user, $remember);
        Guardian::clearTwoFactorChallenge();
        Guardian::clearPendingTwoFactorSetup();

        Session::regenerate();

        return AuthFlowResult::Authenticated;
    }
}
