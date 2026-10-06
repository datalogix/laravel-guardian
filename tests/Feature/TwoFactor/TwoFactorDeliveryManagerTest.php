<?php

namespace Datalogix\Guardian\Tests\Feature\TwoFactor;

use Datalogix\Guardian\Enums\TwoFactorMethod;
use Datalogix\Guardian\Exceptions\TwoFactorDeliveryException;
use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Notifications\TwoFactorCodeNotification;
use Datalogix\Guardian\Notifications\TwoFactorSmsCodeNotification;
use Datalogix\Guardian\Support\TwoFactor\TwoFactorDeliveryManager;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

class TwoFactorDeliveryManagerTest extends TestCase
{
    protected TwoFactorDeliveryManager $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = app(TwoFactorDeliveryManager::class);
    }

    public function test_totp_method_dispatches_nothing(): void
    {
        Notification::fake();

        $user = $this->createUser();

        $this->manager->dispatch(Fortress::make()->basic(), $user, TwoFactorMethod::Totp, '123456', 'challenge');

        Notification::assertNothingSent();
    }

    public function test_email_method_notifies_the_users_email(): void
    {
        Notification::fake();

        $user = $this->createUser(['email' => 'code@example.com']);

        $this->manager->dispatch(Fortress::make()->basic(), $user, TwoFactorMethod::Email, '123456', 'challenge');

        Notification::assertSentOnDemand(
            TwoFactorCodeNotification::class,
            fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'code@example.com'
        );
    }

    public function test_email_method_uses_a_custom_sender_when_given(): void
    {
        $called = false;
        $user = $this->createUser();

        $this->manager->dispatch(
            Fortress::make()->basic(),
            $user,
            TwoFactorMethod::Email,
            '123456',
            'challenge',
            sendEmailCodeUsing: function ($sentUser, $code, $context, $fortress, $email) use (&$called, $user) {
                $called = true;
                $this->assertTrue($sentUser->is($user));
                $this->assertSame('123456', $code);
            },
        );

        $this->assertTrue($called);
    }

    public function test_sms_method_throws_when_vonage_channel_is_unavailable(): void
    {
        $user = $this->createUser();
        $user->setAttribute('phone', '+15551234567');

        $this->expectException(TwoFactorDeliveryException::class);
        $this->expectExceptionMessage(TwoFactorDeliveryException::unavailableMethod(TwoFactorMethod::Sms)->getMessage());

        $this->manager->dispatch(Fortress::make()->basic(), $user, TwoFactorMethod::Sms, '123456', 'challenge');
    }

    public function test_sms_method_throws_when_no_recipient_can_be_resolved(): void
    {
        $user = $this->createUser();

        $this->expectException(TwoFactorDeliveryException::class);
        $this->expectExceptionMessage(TwoFactorDeliveryException::missingRecipient(TwoFactorMethod::Sms)->getMessage());

        $this->manager->dispatch(Fortress::make()->basic(), $user, TwoFactorMethod::Sms, '123456', 'challenge');
    }

    public function test_sms_method_uses_a_custom_sender_when_given(): void
    {
        $called = false;
        $user = $this->createUser();

        $this->manager->dispatch(
            Fortress::make()->basic(),
            $user,
            TwoFactorMethod::Sms,
            '123456',
            'challenge',
            sendSmsCodeUsing: function ($sentUser, $recipient, $code, $context, $fortress) use (&$called) {
                $called = true;
            },
            resolveSmsRecipientUsing: fn ($user, $fortress) => '+15551234567',
        );

        $this->assertTrue($called);
    }

    public function test_email_method_throws_when_no_recipient_can_be_resolved(): void
    {
        $user = $this->createUser(['email' => '']);

        $this->expectException(TwoFactorDeliveryException::class);
        $this->expectExceptionMessage(TwoFactorDeliveryException::missingRecipient(TwoFactorMethod::Email)->getMessage());

        $this->manager->dispatch(Fortress::make()->basic(), $user, TwoFactorMethod::Email, '123456', 'challenge');
    }

    #[RunInSeparateProcess]
    public function test_sms_method_notifies_via_vonage_when_the_channel_is_available(): void
    {
        require __DIR__.'/../../Fixtures/stubs/VonageMessage.php';

        Notification::fake();

        $user = $this->createUser();
        $user->setAttribute('phone', '+15551234567');

        $this->manager->dispatch(Fortress::make()->basic(), $user, TwoFactorMethod::Sms, '123456', 'challenge');

        Notification::assertSentOnDemand(
            TwoFactorSmsCodeNotification::class,
            fn ($notification, $channels, $notifiable) => $notifiable->routes['vonage'] === '+15551234567'
        );
    }
}
