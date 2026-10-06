<?php

namespace Datalogix\Guardian\Tests\Feature\Framework\Livewire\Pages;

use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Framework\Livewire\Pages\EmailVerificationPrompt;
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
}
