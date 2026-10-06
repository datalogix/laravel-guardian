<?php

namespace Datalogix\Guardian\Tests\Feature\Actions;

use Datalogix\Guardian\Actions\SendEmailVerificationNotification;
use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;

class SendEmailVerificationNotificationActionTest extends TestCase
{
    protected function fortresses(): array
    {
        return [Fortress::make()->product()->default()];
    }

    public function test_it_sends_the_verification_notification_for_unverified_users(): void
    {
        Notification::fake();

        $user = $this->createUser(['email_verified_at' => null]);

        $result = app(SendEmailVerificationNotification::class)($user);

        $this->assertTrue($result);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_it_does_nothing_for_already_verified_users(): void
    {
        Notification::fake();

        $user = $this->createUser(['email_verified_at' => now()]);

        $result = app(SendEmailVerificationNotification::class)($user);

        $this->assertFalse($result);
        Notification::assertNothingSent();
    }

    public function test_it_does_nothing_when_email_verification_is_not_enabled(): void
    {
        // A single call site can't cross fortresses, so this asserts the guard
        // for the "verify route disabled" branch via a feature toggled off.
        Guardian::getEmailVerificationVerifyFeature()->configure(
            false, null, null, null, null, null
        );

        Notification::fake();

        $user = $this->createUser(['email_verified_at' => null]);

        $result = app(SendEmailVerificationNotification::class)($user);

        $this->assertFalse($result);
        Notification::assertNothingSent();
    }
}
