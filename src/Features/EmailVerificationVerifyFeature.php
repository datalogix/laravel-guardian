<?php

namespace Datalogix\Guardian\Features;

use Datalogix\Guardian\Http\Controllers\EmailVerificationController;
use Datalogix\Guardian\Http\Responses\EmailVerificationVerifyResponse;
use Illuminate\Support\Facades\Route;

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
        Route::get($this->getRouteSlug().'/{id}/{hash}', $this->getRouteAction())
            ->middleware($this->fortress->getAuthMiddleware())
            ->middleware(array_filter([
                'signed',
                $this->getMaxAttempts() ? 'throttle:'.$this->getMaxAttempts().',1' : null,
            ]))
            ->name($this->getRouteName());
    }
}
