<?php

namespace Datalogix\Guardian\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TwoFactorCodeMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        protected string $code,
        protected string $context,
    ) {}

    public function build(): static
    {
        $contextLabel = $this->context === 'setup' ? 'setup' : 'login';

        return $this
            ->subject('Your two-factor authentication code')
            ->text('guardian::emails.two-factor-code-text', [
                'code' => $this->code,
                'contextLabel' => $contextLabel,
            ]);
    }
}
