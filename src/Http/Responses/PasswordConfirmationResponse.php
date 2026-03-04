<?php

namespace Datalogix\Guardian\Http\Responses;

use Datalogix\Guardian\Response\Redirector;
use Illuminate\Contracts\Support\Responsable;

class PasswordConfirmationResponse implements Responsable
{
    public function toResponse($request)
    {
        return Redirector::redirectIntended();
    }
}
