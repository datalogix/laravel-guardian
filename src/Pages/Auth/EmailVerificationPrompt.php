<?php

namespace Datalogix\Fortress\Pages\Auth;

use Datalogix\Fortress\Facades\Fortress;
use Datalogix\Fortress\Http\Responses\Auth\Contracts\EmailVerificationResponse;
use Datalogix\Fortress\Pages\Page;
use Illuminate\Contracts\Auth\MustVerifyEmail;

class EmailVerificationPrompt extends Page
{
    public function mount(): void
    {
        if ($this->getVerifiable()->hasVerifiedEmail()) {
            app(EmailVerificationResponse::class);
        }
    }

    protected function getVerifiable(): MustVerifyEmail
    {
        /** @var MustVerifyEmail */
        $user = Fortress::user();

        return $user;
    }
}
