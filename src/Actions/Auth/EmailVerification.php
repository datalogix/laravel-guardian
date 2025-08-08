<?php

namespace Datalogix\Fortress\Actions\Auth;

use Datalogix\Fortress\Http\Responses\Auth\Contracts\EmailVerificationResponse;
use Illuminate\Foundation\Auth\EmailVerificationRequest;

class EmailVerification
{
    public function __invoke(EmailVerificationRequest $request): ?EmailVerificationResponse
    {
        $request->fulfill();

        return app(EmailVerificationResponse::class);
    }
}
