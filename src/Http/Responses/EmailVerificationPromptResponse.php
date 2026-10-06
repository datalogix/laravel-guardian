<?php

namespace Datalogix\Guardian\Http\Responses;

use Datalogix\Guardian\Response\Notifier;
use Illuminate\Contracts\Support\Responsable;

class EmailVerificationPromptResponse implements Responsable
{
    public function __construct(
        protected $sent,
        protected ?int $retryAfter = null,
    ) {}

    public function toResponse($request)
    {
        if ($this->retryAfter !== null) {
            Notifier::notify(__('Too many attempts. Please try again in :seconds seconds.', ['seconds' => $this->retryAfter]), 'danger');

            return;
        }

        Notifier::notify(
            $this->sent ? 'Verification link sent!' : 'Failed to send verification link. Please try again later.',
            $this->sent ? 'success' : 'danger'
        );
    }
}
