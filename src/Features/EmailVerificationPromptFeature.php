<?php

namespace Datalogix\Guardian\Features;

use Datalogix\Guardian\Http\Responses\EmailVerificationPromptResponse;

class EmailVerificationPromptFeature extends Feature
{
    protected function defaultRouteAction()
    {
        return $this->resolveComponent('email-verification-prompt');
    }

    protected function defaultRouteSlug(): string
    {
        return 'email-verification/prompt';
    }

    protected function defaultRouteName(): string
    {
        return 'auth.email-verification.prompt';
    }

    protected function defaultResponse(): string
    {
        return EmailVerificationPromptResponse::class;
    }

    protected function defaultMaxAttempts(): int|false
    {
        return 5;
    }

    protected function pageName(): string
    {
        return 'email-verification-prompt';
    }

    public function registerRoutes(): void
    {
        $this->registerRoute('get', $this->getRouteSlug(), $this->fortress->getAuthMiddleware());
    }
}
