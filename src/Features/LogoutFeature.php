<?php

namespace Datalogix\Guardian\Features;

use Datalogix\Guardian\Http\Controllers\LogoutController;
use Datalogix\Guardian\Http\Responses\LogoutResponse;
use Illuminate\Support\Facades\Route;

class LogoutFeature extends Feature
{
    protected function defaultRouteAction()
    {
        return LogoutController::class;
    }

    protected function defaultRouteSlug(): string
    {
        return 'logout';
    }

    protected function defaultRouteName(): string
    {
        return 'auth.logout';
    }

    protected function defaultResponse(): string
    {
        return LogoutResponse::class;
    }

    protected function defaultMaxAttempts(): int|false
    {
        return false;
    }

    protected function pageName(): string
    {
        return 'logout';
    }

    public function registerRoutes(): void
    {
        Route::any($this->getRouteSlug(), $this->getRouteAction())
            ->middleware($this->fortress->getAuthMiddleware())
            ->name($this->getRouteName());
    }
}
