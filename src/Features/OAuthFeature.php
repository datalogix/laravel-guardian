<?php

namespace Datalogix\Guardian\Features;

use Datalogix\Guardian\Http\Controllers\OAuthController;
use Datalogix\Guardian\Http\Middleware\RedirectIfAuthenticated;
use Datalogix\Guardian\Http\Responses\LoginResponse;
use Illuminate\Support\Facades\Route;

class OAuthFeature extends Feature
{
    protected function defaultRouteAction()
    {
        return OAuthController::class;
    }

    protected function defaultRouteSlug(): string
    {
        return 'oauth';
    }

    protected function defaultRouteName(): string
    {
        return 'auth.oauth';
    }

    protected function defaultResponse(): string
    {
        return LoginResponse::class;
    }

    protected function defaultMaxAttempts(): int|false
    {
        return 30;
    }

    protected function pageName(): string
    {
        return 'oauth';
    }

    public function getRedirectUrl(string $provider): ?string
    {
        return $this->fortress->route($this->getRouteName().'.redirect', ['provider' => $provider]);
    }

    public function getCallbackUrl(string $provider): ?string
    {
        return $this->fortress->route($this->getRouteName().'.callback', ['provider' => $provider]);
    }

    public function registerRoutes(): void
    {
        Route::prefix(trim($this->getRouteSlug(), '/'))
            ->name($this->getRouteName().'.')
            ->group(function () {
                Route::get('{provider}/redirect', [$this->getRouteAction(), 'redirect'])
                    ->middleware(array_filter([
                        RedirectIfAuthenticated::class,
                        $this->getMaxAttempts() ? 'throttle:'.$this->getMaxAttempts().',1' : null,
                    ]))
                    ->where('provider', '[A-Za-z0-9_-]+')
                    ->name('redirect');

                Route::get('{provider}/callback', [$this->getRouteAction(), 'callback'])
                    ->middleware(array_filter([
                        RedirectIfAuthenticated::class,
                        $this->getMaxAttempts() ? 'throttle:'.$this->getMaxAttempts().',1' : null,
                    ]))
                    ->where('provider', '[A-Za-z0-9_-]+')
                    ->name('callback');
            });
    }
}
