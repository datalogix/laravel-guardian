<?php

namespace Datalogix\Guardian\Http\Controllers;

use Datalogix\Guardian\Actions\OAuthCallback as OAuthCallbackAction;
use Datalogix\Guardian\Actions\OAuthRedirect as OAuthRedirectAction;
use Datalogix\Guardian\Exceptions\OAuthException;
use Datalogix\Guardian\Guardian;

class OAuthController
{
    public function redirect(string $provider)
    {
        return app(OAuthRedirectAction::class)($provider);
    }

    public function callback(string $provider)
    {
        try {
            $requiresTwoFactorChallenge = app(OAuthCallbackAction::class)($provider);

            if ($requiresTwoFactorChallenge) {
                return app(Guardian::getTwoFactorChallengeFeature()->getResponse());
            }

            return app(Guardian::getOAuthFeature()->getResponse());
        } catch (OAuthException $exception) {
            return redirect()->to(Guardian::getLoginFeature()->getUrl())
                ->withErrors($exception->errors());
        }
    }
}
