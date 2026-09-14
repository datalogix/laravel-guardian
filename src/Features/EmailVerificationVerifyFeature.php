<?php

namespace Datalogix\Guardian\Features;

use Datalogix\Guardian\Http\Controllers\EmailVerificationController;
use Datalogix\Guardian\Http\Responses\EmailVerificationVerifyResponse;

class EmailVerificationVerifyFeature extends Feature
{
    protected function defaultRouteAction()
    {
        return EmailVerificationController::class;
    }

    protected function defaultRouteSlug(): string
    {
        return 'email-verification/verify';
    }

    protected function defaultRouteName(): string
    {
        return 'auth.email-verification.verify';
    }

    protected function defaultResponse(): string
    {
        return EmailVerificationVerifyResponse::class;
    }

    protected function defaultMaxAttempts(): int|false
    {
        return 5;
    }

    protected function pageName(): string
    {
        return 'email-verification-verify';
    }

    public function registerRoutes(): void
    {
        $this->registerRoute('get', $this->getRouteSlug().'/{id}/{hash}', [
            ...$this->fortress->getAuthMiddleware(),
            'signed',
            $this->throttleMiddleware(),
        ]);
    }
}
