<?php

namespace Datalogix\Guardian\Http\Livewire;

use Datalogix\Guardian\Actions\SendEmailVerificationNotification;
use Datalogix\Guardian\Guardian;
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
            Guardian::redirect(intended: true);
        }
    }

    public function submit()
    {
        $sent = app(SendEmailVerificationNotification::class)(Guardian::user());

        return app(Guardian::getEmailVerificationPromptResponse(), ['sent' => $sent]);
    }
}
