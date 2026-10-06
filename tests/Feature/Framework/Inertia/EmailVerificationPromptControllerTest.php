<?php

namespace Datalogix\Guardian\Tests\Feature\Framework\Inertia;

use Datalogix\Guardian\Guardian;
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

    public function test_asking_again_too_soon_tells_how_long_to_wait(): void
    {
        Notification::fake();
        $this->actingAs($this->createUser(['email_verified_at' => null]));
        $limit = Guardian::getEmailVerificationPromptFeature()->getMaxAttempts();

        for ($i = 0; $i < $limit; $i++) {
            $this->from('/email-verification/prompt')->inertiaPost('/email-verification/prompt');
        }

        $this->from('/email-verification/prompt')->inertiaPost('/email-verification/prompt')
            ->assertSessionHas('status', fn (string $status) => str_starts_with($status, 'Too many attempts. Please try again in '));
    }
}
