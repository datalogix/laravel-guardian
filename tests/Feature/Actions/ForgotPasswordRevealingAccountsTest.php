<?php

namespace Datalogix\Guardian\Tests\Feature\Actions;

use Datalogix\Guardian\Actions\ForgotPassword;
use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Tests\Fixtures\RecordingTimebox;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Timebox;

/**
 * A fortress that chooses to tell the visitor whether the account exists.
 */
class ForgotPasswordRevealingAccountsTest extends TestCase
{
    protected function fortresses(): array
    {
        return [Fortress::make()->basic()->passwordReset(revealsAccounts: true)];
    }

    public function test_it_tells_apart_an_existing_account_an_unknown_one_and_a_throttled_request(): void
    {
        Notification::fake();

        $user = $this->createUser();
        $action = app(ForgotPassword::class);

        $this->assertSame(Password::RESET_LINK_SENT, $action(['login' => $user->email]));
        $this->assertSame(Password::RESET_THROTTLED, $action(['login' => $user->email]));
        $this->assertSame(Password::INVALID_USER, $action(['login' => 'nobody@example.com']));
    }

    public function test_it_does_not_wait_for_the_timebox(): void
    {
        Notification::fake();
        $timebox = new RecordingTimebox;
        $this->app->instance(Timebox::class, $timebox);

        app(ForgotPassword::class)(['login' => 'nobody@example.com']);

        $this->assertSame([], $timebox->durations);
    }
}
