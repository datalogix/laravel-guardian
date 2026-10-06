<?php

namespace Datalogix\Guardian\Tests\Feature\Notifications;

use Datalogix\Guardian\Notifications\TwoFactorCodeNotification;
use Datalogix\Guardian\Notifications\TwoFactorSmsCodeNotification;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\VonageMessage;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

class TwoFactorNotificationsTest extends TestCase
{
    public function test_code_notification_is_queued_and_sent_by_mail(): void
    {
        $notification = new TwoFactorCodeNotification('123456', 'challenge');

        $this->assertInstanceOf(ShouldQueue::class, $notification);
        $this->assertSame(['mail'], $notification->via($this->createUser()));

        $mail = $notification->toMail($this->createUser());
        $this->assertStringContainsString('123456', implode(' ', $mail->introLines + $mail->outroLines));
    }

    public function test_code_notification_mail_mentions_setup_when_in_setup_context(): void
    {
        $notification = new TwoFactorCodeNotification('654321', 'setup');

        $mail = $notification->toMail($this->createUser());

        $this->assertStringContainsString(
            'finish setting up two-factor authentication',
            implode(' ', $mail->introLines)
        );
    }

    public function test_sms_notification_routes_via_vonage(): void
    {
        $notification = new TwoFactorSmsCodeNotification('123456', 'challenge');

        $this->assertSame(['vonage'], $notification->via($this->createUser()));
    }

    public function test_sms_notification_returns_null_when_vonage_channel_is_unavailable(): void
    {
        // VonageMessage does not exist here: toVonage() must degrade gracefully.
        $notification = new TwoFactorSmsCodeNotification('123456', 'challenge');

        $this->assertNull($notification->toVonage($this->createUser()));
    }

    #[RunInSeparateProcess]
    public function test_sms_notification_builds_a_vonage_message_when_the_channel_is_available(): void
    {
        require __DIR__.'/../../Fixtures/stubs/VonageMessage.php';

        $notification = new TwoFactorSmsCodeNotification('123456', 'challenge');

        $message = $notification->toVonage($this->createUser());

        $this->assertInstanceOf(VonageMessage::class, $message);
        $this->assertStringContainsString('123456', $message->content);
    }

    #[RunInSeparateProcess]
    public function test_sms_notification_mentions_setup_when_in_setup_context_via_vonage(): void
    {
        require __DIR__.'/../../Fixtures/stubs/VonageMessage.php';

        $notification = new TwoFactorSmsCodeNotification('654321', 'setup');

        $message = $notification->toVonage($this->createUser());

        $this->assertStringContainsString('two-factor setup code', $message->content);
    }
}
