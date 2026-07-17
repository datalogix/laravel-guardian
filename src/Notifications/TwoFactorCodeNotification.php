<?php

namespace Datalogix\Guardian\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TwoFactorCodeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected string $code,
        protected string $context,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $contextLabel = $this->context === 'setup' ? 'setup' : 'login';

        return (new MailMessage)
            ->subject('Your two-factor authentication code')
            ->view('guardian::emails.two-factor-code-html', [
                'code' => $this->code,
                'contextLabel' => $contextLabel,
            ])
            ->text('guardian::emails.two-factor-code-text', [
                'code' => $this->code,
                'contextLabel' => $contextLabel,
            ]);
    }
}
