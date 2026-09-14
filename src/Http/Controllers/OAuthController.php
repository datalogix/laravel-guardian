<?php

namespace Datalogix\Guardian\Http\Controllers;

use Datalogix\Guardian\Actions\OAuthCallback as OAuthCallbackAction;
use Datalogix\Guardian\Actions\OAuthRedirect as OAuthRedirectAction;
use Datalogix\Guardian\Exceptions\OAuthException;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Response\Redirector;

class OAuthController
{
    public function redirect(string $provider)
    {
        try {
            return app(OAuthRedirectAction::class)($provider);
        } catch (OAuthException $exception) {
            return Redirector::redirectToLogin()
                ->withErrors($exception->errors());
        }
    }

    public function callback(string $provider)
    {
        try {
            $result = app(OAuthCallbackAction::class)($provider);

            return Guardian::respondToAuthFlow($result, Guardian::getOAuthFeature()->getResponse());
        } catch (OAuthException $exception) {
            return Redirector::redirectToLogin()
                ->withErrors($exception->errors());
        }
    }
}
