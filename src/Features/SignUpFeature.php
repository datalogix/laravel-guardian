<?php

namespace Datalogix\Guardian\Features;

use Datalogix\Guardian\Http\Middleware\RedirectIfAuthenticated;
use Datalogix\Guardian\Http\Responses\SignUpResponse;
use Illuminate\Support\Facades\Route;

class SignUpFeature extends Feature
{
    protected function defaultRouteAction()
    {
        return $this->resolveComponent('sign-up');
    }

    protected function defaultRouteSlug(): string
    {
        return 'sign-up';
    }

    protected function defaultRouteName(): string
    {
        return 'auth.sign-up';
    }

    protected function defaultResponse(): string
    {
        return SignUpResponse::class;
    }

    protected function defaultMaxAttempts(): int|false
    {
        return 2;
    }

    protected function pageName(): string
    {
        return 'sign-up';
    }

    public function registerRoutes(): void
    {
        if ($this->hasFeature()) {
            Route::get($this->getRouteSlug(), $this->getRouteAction())
                ->middleware(RedirectIfAuthenticated::class)
                ->name($this->getRouteName());
        }
    }
}
