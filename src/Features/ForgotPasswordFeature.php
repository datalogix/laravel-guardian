<?php

namespace Datalogix\Guardian\Features;

use Datalogix\Guardian\Http\Middleware\RedirectIfAuthenticated;
use Datalogix\Guardian\Http\Responses\ForgotPasswordResponse;
use Illuminate\Support\Facades\Route;

class ForgotPasswordFeature extends Feature
{
    protected function defaultRouteAction()
    {
        return $this->resolveComponent('forgot-password');
    }

    protected function defaultRouteSlug(): string
    {
        return 'forgot-password';
    }

    protected function defaultRouteName(): string
    {
        return 'auth.password.request';
    }

    protected function defaultResponse(): string
    {
        return ForgotPasswordResponse::class;
    }

    protected function defaultMaxAttempts(): int|false
    {
        return 3;
    }

    protected function pageName(): string
    {
        return 'forgot-password';
    }

    public function registerRoutes(): void
    {
        if ($this->hasFeature()) {
            Route::middleware(RedirectIfAuthenticated::class)->group(function () {
                Route::get($this->getRouteSlug(), $this->getRouteAction())
                    ->name($this->getRouteName());
            });
        }
    }
}
