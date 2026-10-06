<?php

namespace Datalogix\Guardian\Notifications;

use Datalogix\Guardian\Notifications\Concerns\HasTwoFactorCodeContext;
use Datalogix\Guardian\Notifications\Concerns\QueuesTwoFactorCode;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class TwoFactorSmsCodeNotification extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use HasTwoFactorCodeContext;
    use Queueable;
    use QueuesTwoFactorCode;

    public function __construct(
        public readonly string $code,
        protected string $context,
    ) {
        $this->queueTwoFactorCode();
    }

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
