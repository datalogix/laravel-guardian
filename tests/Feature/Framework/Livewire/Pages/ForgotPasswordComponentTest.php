<?php

namespace Datalogix\Guardian\Tests\Feature\Framework\Livewire\Pages;

use Datalogix\Guardian\Actions\ForgotPassword as ForgotPasswordAction;
use Datalogix\Guardian\Framework\Livewire\Pages\ForgotPassword;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Group;

#[Group('livewire')]
class ForgotPasswordComponentTest extends TestCase
{
    public function test_it_renders(): void
    {
        Livewire::test(ForgotPassword::class)->assertOk();
    }

    public function test_submit_sends_a_reset_link(): void
    {
        Notification::fake();

        $user = $this->createUser();

        Livewire::test(ForgotPassword::class)
            ->set('login', $user->email)
            ->call('submit')
            ->assertOk();

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_submit_gives_the_same_answer_for_an_unknown_login(): void
    {
        Notification::fake();

        Livewire::test(ForgotPassword::class)
            ->set('login', 'nobody@example.com')
            ->call('submit')
            ->assertHasNoErrors();

        $this->assertSame(__(ForgotPasswordAction::GENERIC_STATUS), session('status'));
        Notification::assertNothingSent();
    }

    public function test_submit_validates_the_login_field(): void
    {
        Livewire::test(ForgotPassword::class)
            ->set('login', 'not-an-email')
            ->call('submit')
            ->assertHasErrors('login');
    }
}
