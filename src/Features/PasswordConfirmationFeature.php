<?php

namespace Datalogix\Guardian\Features;

use Datalogix\Guardian\Http\Responses\PasswordConfirmationResponse;

class PasswordConfirmationFeature extends Feature
{
    protected function defaultRouteAction()
    {
        return $this->resolveComponent('confirm-password');
    }

    protected function defaultRouteSlug(): string
    {
        return 'confirm-password';
    }

    protected function defaultRouteName(): string
    {
        return 'auth.password.confirm';
    }

    protected function defaultResponse(): string
    {
        return PasswordConfirmationResponse::class;
    }

    protected function defaultMaxAttempts(): int|false
    {
        return 5;
    }

    protected function pageName(): string
    {
        return 'confirm-password';
    }

    public function registerRoutes(): void
    {
        $this->registerRoute('get', $this->getRouteSlug(), $this->fortress->getAuthMiddleware());
    }
}
