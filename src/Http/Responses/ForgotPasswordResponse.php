<?php

namespace Datalogix\Guardian\Http\Responses;

use Datalogix\Guardian\Response\Notifier;
use Illuminate\Contracts\Support\Responsable;

class ForgotPasswordResponse implements Responsable
{
    public function __construct(
        protected string $status
    ) {}

    public function toResponse($request)
    {
        Notifier::notify($this->status);
    }
}
