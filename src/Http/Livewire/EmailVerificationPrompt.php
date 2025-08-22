<?php

namespace Datalogix\Guardian\Http\Livewire;

use Datalogix\Guardian\Actions\SendEmailVerificationNotification;
use Datalogix\Guardian\Guardian;
use Illuminate\Support\Facades\Session;

class EmailVerificationPrompt extends Page
{
    public function mount()
    {
        if (Guardian::user()->hasVerifiedEmail()) {
            Guardian::redirect(intended: true);
        }
    }

    public function submit()
    {
        $result = app(SendEmailVerificationNotification::class)(Guardian::user());

        Session::flash('status', $result ? 'Verification link sent!' : 'Failed to send verification link. Please try again later.');
        Session::flash('status_type', $result ? 'success' : 'danger');
    }
}
