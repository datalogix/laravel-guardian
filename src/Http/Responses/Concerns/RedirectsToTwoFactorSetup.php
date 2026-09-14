<?php

namespace Datalogix\Guardian\Http\Responses\Concerns;

use Datalogix\Guardian\Actions\Logout;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Response\Redirector;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Model;

trait RedirectsToTwoFactorSetup
{
    protected function shouldRedirectToTwoFactorSetup(): bool
    {
        if (Guardian::hasPendingTwoFactorSetup()) {
            return true;
        }

        if (! Guardian::getTwoFactorSetupFeature()->hasFeature()) {
            return false;
        }

        $user = Guardian::user();

        if (! $user instanceof Model) {
            return false;
        }

        return Guardian::requiresTwoFactorSetup($user);
    }

    protected function requiresEmailVerificationPromptRedirect(): bool
    {
        if (! Guardian::isEmailVerificationRequired()) {
            return false;
        }

        $user = Guardian::user();

        return $user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail();
    }

    protected function redirectForRequiredEmailVerification(): mixed
    {
        if (! $this->requiresEmailVerificationPromptRedirect()) {
            return null;
        }

        if (Guardian::getEmailVerificationPromptFeature()->hasFeature()) {
            return Redirector::redirect(Guardian::getEmailVerificationPromptFeature()->getUrl(), false);
        }

        app(Logout::class)();

        return Redirector::redirectToLogin();
    }
}
