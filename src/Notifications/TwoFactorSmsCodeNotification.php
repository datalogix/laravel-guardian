<?php

namespace Datalogix\Guardian\Notifications;

use Datalogix\Guardian\Notifications\Concerns\HasTwoFactorCodeContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class TwoFactorSmsCodeNotification extends Notification implements ShouldQueue
{
    use HasTwoFactorCodeContext;
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

        $content = $this->isSetupContext()
            ? __('Your two-factor setup code is: :code', ['code' => $this->code])
            : __('Your two-factor login code is: :code', ['code' => $this->code]);

        return (new $messageClass)->content($content);
    }
}
