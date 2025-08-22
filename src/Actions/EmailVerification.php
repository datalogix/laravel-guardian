<?php

namespace Datalogix\Guardian\Actions;

use Datalogix\Guardian\Guardian;
use Illuminate\Foundation\Auth\EmailVerificationRequest;

class EmailVerification
{
    public function __invoke(EmailVerificationRequest $request)
    {
        $request->fulfill();

        return app(Guardian::getEmailVerificationResponse());
    }
}
