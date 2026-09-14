<?php

namespace Datalogix\Guardian\Features;

use Datalogix\Guardian\Http\Middleware\EnsureTwoFactorChallengeAccess;
use Datalogix\Guardian\Http\Middleware\RedirectIfAuthenticated;
use Datalogix\Guardian\Http\Responses\TwoFactorChallengeResponse;

class TwoFactorChallengeFeature extends Feature
{
    protected function defaultRouteAction()
    {
        return $this->resolveComponent('two-factor-challenge');
    }

    protected function defaultRouteSlug(): string
    {
        return 'two-factor/challenge';
    }

    protected function defaultRouteName(): string
    {
        return 'auth.two-factor.challenge';
    }

    protected function defaultResponse(): string
    {
        return TwoFactorChallengeResponse::class;
    }

    protected function defaultMaxAttempts(): int|false
    {
        return 5;
    }

    protected function pageName(): string
    {
        return 'two-factor-challenge';
    }

    public function registerRoutes(): void
    {
        $this->registerRoute('get', $this->getRouteSlug(), [
            RedirectIfAuthenticated::class,
            EnsureTwoFactorChallengeAccess::class,
        ]);
    }
}
