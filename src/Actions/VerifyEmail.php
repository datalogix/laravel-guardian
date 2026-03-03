<?php

namespace Datalogix\Guardian\Actions;

use Illuminate\Foundation\Auth\EmailVerificationRequest;

class VerifyEmail
{
    public function __invoke(EmailVerificationRequest $request)
    {
        $request->fulfill();
    }
}
