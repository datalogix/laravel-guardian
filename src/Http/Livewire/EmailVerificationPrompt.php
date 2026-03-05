<?php

namespace Datalogix\Guardian\Http\Livewire;

use Datalogix\Guardian\Actions\SendEmailVerificationNotification;
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
        $sent = app(SendEmailVerificationNotification::class)(Guardian::user());

        return app(Guardian::getEmailVerificationPromptFeature()->getResponse(), ['sent' => $sent]);
    }
}
