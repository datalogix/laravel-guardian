<?php

namespace Datalogix\Guardian\Http\Responses;

use Datalogix\Guardian\Guardian;
use Illuminate\Contracts\Support\Responsable;

class ResetPasswordResponse implements Responsable
{
    public function toResponse($request)
    {
        return Guardian::redirect(Guardian::hasLogin() ? Guardian::getLoginUrl() : null);
    }
}
