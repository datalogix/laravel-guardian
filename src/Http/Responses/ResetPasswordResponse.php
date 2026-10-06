<?php

namespace Datalogix\Guardian\Http\Responses;

use Datalogix\Guardian\Response\Notifier;
use Datalogix\Guardian\Response\Redirector;
use Illuminate\Contracts\Support\Responsable;

class ResetPasswordResponse implements Responsable
{
    public function __construct(
        protected string $status
    ) {}

    public function toResponse($request)
    {
        Notifier::notify($this->status);

        return Redirector::redirectToLogin();
    }
}
