<?php

namespace Datalogix\Guardian\Features;

use Datalogix\Guardian\Http\Middleware\RedirectIfAuthenticated;
use Datalogix\Guardian\Http\Responses\ResetPasswordResponse;

class ResetPasswordFeature extends Feature
{
    protected function defaultRouteAction()
    {
        return $this->resolveComponent('reset-password');
    }

    protected function defaultRouteSlug(): string
    {
        return 'reset-password';
    }

    protected function defaultRouteName(): string
    {
        return 'auth.password.reset';
    }

    protected function defaultResponse(): string
    {
        return ResetPasswordResponse::class;
    }

    protected function defaultMaxAttempts(): int|false
    {
        return 5;
    }

    protected function pageName(): string
    {
        return 'reset-password';
    }

    public function registerRoutes(): void
    {
        $this->registerRoute('get', $this->getRouteSlug().'/{token?}', [RedirectIfAuthenticated::class, 'signed']);
    }
}
