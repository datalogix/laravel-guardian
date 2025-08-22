<?php

namespace Datalogix\Guardian\Http\Responses;

use Datalogix\Guardian\Guardian;
use Illuminate\Contracts\Support\Responsable;

class SignUpResponse implements Responsable
{
    public function toResponse($request)
    {
        return Guardian::redirect(intended: true);
    }
}
