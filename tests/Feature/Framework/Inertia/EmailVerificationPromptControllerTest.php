<?php

namespace Datalogix\Guardian\Tests\Feature\Framework\Inertia;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Group;

#[Group('inertia')]
class EmailVerificationPromptControllerTest extends InertiaTestCase
{
    public function test_it_renders_for_unverified_users(): void
    {
        $this->actingAs($this->createUser(['email_verified_at' => null]));

        $this->assertPage($this->inertiaGet('/email-verification/prompt'), 'Guardian/EmailVerificationPrompt', [
            'logoutUrl' => url('/logout'),
            'endpoints.submit' => url('/email-verification/prompt'),
        ]);
    }

    public function test_verified_users_are_redirected_away(): void
    {
        $this->actingAs($this->createUser(['email_verified_at' => now()]));

        $this->inertiaGet('/email-verification/prompt')->assertRedirect();
    }

    public function test_submit_resends_the_notification_and_stays_on_the_page(): void
    {
        Notification::fake();
        $user = $this->createUser(['email_verified_at' => null]);
        $this->actingAs($user);

        $this->from('/email-verification/prompt')
            ->inertiaPost('/email-verification/prompt')
            ->assertRedirect('/email-verification/prompt')
            ->assertSessionHas('status');

        Notification::assertSentTo($user, VerifyEmail::class);
    }
}
