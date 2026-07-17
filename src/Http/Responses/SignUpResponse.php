<?php

namespace Datalogix\Guardian\Http\Responses;

use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Response\Redirector;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Support\Facades\Session;

class SignUpResponse implements Responsable
{
    public function toResponse($request)
    {
        if (! $this->requiresEmailVerificationPromptRedirect()) {
            return Redirector::redirectIntended();
        }

        if (Guardian::getEmailVerificationPromptFeature()->hasFeature()) {
            return Redirector::redirect(Guardian::getEmailVerificationPromptFeature()->getUrl(), false);
        }

        Guardian::auth()->logout();
        Session::invalidate();
        Session::regenerateToken();

        return Redirector::redirectToLogin();
    }

    protected function requiresEmailVerificationPromptRedirect(): bool
    {
        if (! Guardian::isEmailVerificationRequired()) {
            return false;
        }

        $user = Guardian::user();

        return $user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail();
    }
}
