<?php

namespace Datalogix\Guardian\Framework\Livewire\Pages;

use Datalogix\Guardian\Actions\SendEmailVerificationNotification;
use Datalogix\Guardian\Exceptions\EmailVerificationThrottledException;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Response\Redirector;
use Illuminate\Contracts\Auth\MustVerifyEmail;

class EmailVerificationPrompt extends Page
{
    public function mount()
    {
        $user = Guardian::user();

        if (! $user instanceof MustVerifyEmail) {
            return;
        }

        if ($user->hasVerifiedEmail()) {
            Redirector::redirectIntended();

            return;
        }
    }

    public function submit()
    {
        try {
            $sent = app(SendEmailVerificationNotification::class)(Guardian::user());
        } catch (EmailVerificationThrottledException $exception) {
            return app(Guardian::getEmailVerificationPromptFeature()->getResponse(), ['sent' => false, 'retryAfter' => $exception->seconds]);
        }

        return app(Guardian::getEmailVerificationPromptFeature()->getResponse(), ['sent' => $sent]);
    }
}
