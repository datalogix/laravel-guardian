<?php

namespace Datalogix\Guardian\Http\Responses;

use Datalogix\Guardian\Response\Redirector;
use Illuminate\Contracts\Support\Responsable;

class EmailVerificationVerifyResponse implements Responsable
{
    public function toResponse($request)
    {
        return Redirector::redirectIntended();
    }
}
