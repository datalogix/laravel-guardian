<?php

namespace Datalogix\Guardian\Tests\Feature\Framework\Livewire\Pages;

use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Framework\Livewire\Pages\EmailVerificationPrompt;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Http\Responses\EmailVerificationPromptResponse;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Group;

#[Group('livewire')]
class EmailVerificationPromptComponentTest extends TestCase
{
    protected function fortresses(): array
    {
        return [Fortress::make()->product()->default()];
    }

    public function test_it_renders_for_unverified_users(): void
    {
        $this->actingAs($this->createUser(['email_verified_at' => null]));

        Livewire::test(EmailVerificationPrompt::class)->assertOk();
    }

    public function test_submit_resends_the_verification_notification(): void
    {
        Notification::fake();

        $this->actingAs($this->createUser(['email_verified_at' => null]));

        Livewire::test(EmailVerificationPrompt::class)->call('submit');

        Notification::assertSentTimes(VerifyEmail::class, 1);
    }

    public function test_asking_again_too_soon_tells_how_long_to_wait(): void
    {
        Notification::fake();
        $this->actingAs($this->createUser(['email_verified_at' => null]));
        $component = Livewire::test(EmailVerificationPrompt::class);

        for ($i = 0; $i < Guardian::getEmailVerificationPromptFeature()->getMaxAttempts(); $i++) {
            $component->call('submit');
        }

        $parameters = null;
        $this->app->bind(EmailVerificationPromptResponse::class, function ($app, array $given) use (&$parameters) {
            $parameters = $given;

            return new EmailVerificationPromptResponse(...$given);
        });

        $component->call('submit');

        $this->assertFalse($parameters['sent']);
        $this->assertIsInt($parameters['retryAfter']);
        Notification::assertSentTimes(VerifyEmail::class, Guardian::getEmailVerificationPromptFeature()->getMaxAttempts());
    }
}
