<?php

namespace Datalogix\Guardian\Http\Responses;

use Datalogix\Guardian\Guardian;
use Illuminate\Contracts\Support\Responsable;

class ForgotPasswordResponse implements Responsable
{
    public function __construct(
        protected string $status
    ) {}

    public function toResponse($request)
    {
        Guardian::notify($this->status);
    }
}
