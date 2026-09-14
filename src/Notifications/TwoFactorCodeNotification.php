<?php

namespace Datalogix\Guardian\Notifications;

use Datalogix\Guardian\Notifications\Concerns\HasTwoFactorCodeContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TwoFactorCodeNotification extends Notification implements ShouldQueue
{
    use HasTwoFactorCodeContext;
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
        return (new MailMessage)
            ->subject(__('Your two-factor authentication code'))
            ->greeting(__('Hello!'))
            ->line(
                $this->isSetupContext()
                    ? __('Use the verification code below to finish setting up two-factor authentication for your account.')
                    : __('Use the verification code below to complete your sign-in.')
            )
            ->line(__('Verification code: :code', ['code' => $this->code]))
            ->line(__('This code will expire shortly.'))
            ->line(__("If you didn't request this code, no action is needed — you can safely ignore this email."));
    }
}
