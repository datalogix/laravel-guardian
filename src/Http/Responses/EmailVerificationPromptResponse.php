<?php

namespace Datalogix\Guardian\Http\Responses;

use Datalogix\Guardian\Guardian;
use Illuminate\Contracts\Support\Responsable;

class EmailVerificationPromptResponse implements Responsable
{
    public function __construct(
        protected $sent
    ) {}

    public function toResponse($request)
    {
        Guardian::notify(
            $this->sent ? 'Verification link sent!' : 'Failed to send verification link. Please try again later.',
            $this->sent ? 'success' : 'danger'
        );
    }
}
