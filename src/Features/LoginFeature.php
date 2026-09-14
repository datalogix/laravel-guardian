<?php

namespace Datalogix\Guardian\Features;

use Datalogix\Guardian\Http\Middleware\RedirectIfAuthenticated;
use Datalogix\Guardian\Http\Responses\LoginResponse;

class LoginFeature extends Feature
{
    protected function defaultRouteAction()
    {
        return $this->resolveComponent('login');
    }

    protected function defaultRouteSlug(): string
    {
        return 'login';
    }

    protected function defaultRouteName(): string
    {
        return 'auth.login';
    }

    protected function defaultResponse(): string
    {
        return LoginResponse::class;
    }

    protected function defaultMaxAttempts(): int|false
    {
        return 5;
    }

    protected function pageName(): string
    {
        return 'login';
    }

    public function registerRoutes(): void
    {
        $this->registerRoute('get', $this->getRouteSlug(), RedirectIfAuthenticated::class);
    }
}
