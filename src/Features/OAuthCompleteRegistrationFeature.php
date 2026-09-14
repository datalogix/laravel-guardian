<?php

namespace Datalogix\Guardian\Features;

use Datalogix\Guardian\Http\Middleware\EnsureOAuthCompleteRegistrationAccess;
use Datalogix\Guardian\Http\Responses\OAuthCompleteRegistrationResponse;

class OAuthCompleteRegistrationFeature extends Feature
{
    protected function defaultRouteAction()
    {
        return $this->resolveComponent('oauth-complete-registration');
    }

    protected function defaultRouteSlug(): string
    {
        return 'oauth/complete-registration';
    }

    protected function defaultRouteName(): string
    {
        return 'auth.oauth.complete-registration';
    }

    protected function defaultResponse(): string
    {
        return OAuthCompleteRegistrationResponse::class;
    }

    protected function defaultMaxAttempts(): int|false
    {
        return 5;
    }

    protected function pageName(): string
    {
        return 'oauth-complete-registration';
    }

    public function registerRoutes(): void
    {
        $this->registerRoute('get', $this->getRouteSlug(), array_filter([
            EnsureOAuthCompleteRegistrationAccess::class,
            $this->throttleMiddleware(),
        ]));
    }
}
