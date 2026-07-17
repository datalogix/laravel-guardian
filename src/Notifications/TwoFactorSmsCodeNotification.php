<?php

namespace Datalogix\Guardian\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class TwoFactorSmsCodeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected string $code,
        protected string $context,
    ) {}

    public function via(object $notifiable): array
    {
        return ['vonage'];
    }

    public function toVonage(object $notifiable): mixed
    {
        $messageClass = 'Illuminate\\Notifications\\Messages\\VonageMessage';

        if (! class_exists($messageClass)) {
            return null;
        }

        $contextLabel = $this->context === 'setup' ? 'setup' : 'login';

        return (new $messageClass)
            ->content("Your two-factor {$contextLabel} code is {$this->code}");
    }
}
