<?php

namespace Datalogix\Guardian\Http\Responses;

use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Response\Redirector;
use Illuminate\Contracts\Support\Responsable;

class OAuthCompleteRegistrationResponse implements Responsable
{
    public function toResponse($request)
    {
        return Redirector::redirect(Guardian::getOAuthCompleteRegistrationFeature()->getUrl(), false);
    }
}
