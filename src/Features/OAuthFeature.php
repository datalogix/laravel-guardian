<?php

namespace Datalogix\Guardian\Features;

use Closure;
use Datalogix\Guardian\Http\Controllers\OAuthController;
use Datalogix\Guardian\Http\Middleware\RedirectIfAuthenticated;
use Datalogix\Guardian\Http\Responses\LoginResponse;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;

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
        $action = $this->getRouteAction();

        if ($action instanceof Closure || is_array($action)) {
            throw new InvalidArgumentException('The OAuth feature route action must be a single controller class name with redirect() and callback() methods — a Closure or a [class, method] array cannot handle both the redirect and callback routes.');
        }

        Route::prefix(trim($this->getRouteSlug(), '/'))
            ->name($this->getRouteName().'.')
            ->group(function () use ($action) {
                Route::get('{provider}/redirect', [$action, 'redirect'])
                    ->middleware(array_filter([RedirectIfAuthenticated::class, $this->throttleMiddleware()]))
                    ->where('provider', '[A-Za-z0-9_-]+')
                    ->name('redirect');

                Route::get('{provider}/callback', [$action, 'callback'])
                    ->middleware(array_filter([RedirectIfAuthenticated::class, $this->throttleMiddleware()]))
                    ->where('provider', '[A-Za-z0-9_-]+')
                    ->name('callback');
            });
    }
}
