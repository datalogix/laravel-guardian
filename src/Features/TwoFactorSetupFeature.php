<?php

namespace Datalogix\Guardian\Features;

use Datalogix\Guardian\Http\Responses\TwoFactorSetupResponse;
use Illuminate\Support\Facades\Route;

class TwoFactorSetupFeature extends Feature
{
    protected function defaultRouteAction()
    {
        return $this->resolveComponent('two-factor-setup');
    }

    protected function defaultRouteSlug(): string
    {
        return 'two-factor/setup';
    }

    protected function defaultRouteName(): string
    {
        return 'auth.two-factor.setup';
    }

    protected function defaultResponse(): string
    {
        return TwoFactorSetupResponse::class;
    }

    protected function defaultMaxAttempts(): int|false
    {
        return 5;
    }

    protected function pageName(): string
    {
        return 'two-factor-setup';
    }

    public function registerRoutes(): void
    {
        if ($this->hasFeature()) {
            Route::get($this->getRouteSlug(), $this->getRouteAction())
                ->middleware($this->fortress->getAuthMiddleware())
                ->name($this->getRouteName());
        }
    }
}
