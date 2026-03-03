<?php

namespace Datalogix\Guardian\Http\Controllers;

use Datalogix\Guardian\Actions\VerifyEmail;
use Datalogix\Guardian\Guardian;
use Illuminate\Foundation\Auth\EmailVerificationRequest;

class EmailVerificationController
{
    public function __invoke(EmailVerificationRequest $request)
    {
        app(VerifyEmail::class)($request);

        return app(Guardian::getEmailVerificationVerifyResponse());
    }
}
