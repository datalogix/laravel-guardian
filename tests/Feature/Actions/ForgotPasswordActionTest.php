<?php

namespace Datalogix\Guardian\Tests\Feature\Actions;

use Datalogix\Guardian\Actions\ForgotPassword;
use Datalogix\Guardian\Exceptions\ResetPasswordException;
use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Tests\Attributes\WithFortresses;
use Datalogix\Guardian\Tests\Fixtures\RecordingTimebox;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Auth\Events\PasswordResetLinkSent;
use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Timebox;

class ForgotPasswordActionTest extends TestCase
{
    public function test_it_sends_a_reset_link_and_dispatches_an_event(): void
    {
        Notification::fake();
        Event::fake([PasswordResetLinkSent::class]);

        $user = $this->createUser();

        $status = app(ForgotPassword::class)(['login' => $user->email]);

        $this->assertSame(ForgotPassword::GENERIC_STATUS, $status);
        Notification::assertSentTo($user, ResetPasswordNotification::class);
        Event::assertDispatched(PasswordResetLinkSent::class);
    }

    public function test_the_emailed_link_is_a_signed_url_to_the_reset_page_of_the_fortress(): void
    {
        Notification::fake();

        $user = $this->createUser();

        app(ForgotPassword::class)(['login' => $user->email]);

        Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use ($user) {
            $request = Request::create($notification->toMail($user)->actionUrl);

            $this->assertTrue(URL::hasValidSignature($request));
            $this->assertSame($user->email, $request->query('login'));
            $this->assertStringEndsWith('/'.$notification->token, $request->path());

            return true;
        });
    }

    public function test_it_silently_returns_for_users_who_cannot_access_the_fortress(): void
    {
        Notification::fake();

        $user = $this->createUser(['can_access' => false]);

        app(ForgotPassword::class)(['login' => $user->email]);

        Notification::assertNothingSent();
    }

    public function test_it_is_rate_limited(): void
    {
        Notification::fake();

        $user = $this->createUser();
        $maxAttempts = Guardian::getForgotPasswordFeature()->getMaxAttempts();

        for ($i = 0; $i < $maxAttempts; $i++) {
            app(ForgotPassword::class)(['login' => $user->email]);
        }

        $this->expectRateLimitedBy(ResetPasswordException::class);

        app(ForgotPassword::class)(['login' => $user->email]);
    }

    public function test_rules_validate_the_identifier(): void
    {
        $this->assertArrayHasKey('login', ForgotPassword::rules());
    }

    public function test_it_answers_the_same_whether_or_not_the_account_exists(): void
    {
        Notification::fake();

        $user = $this->createUser();
        $action = app(ForgotPassword::class);

        $known = $action(['login' => $user->email]);
        $throttledByTheBroker = $action(['login' => $user->email]);
        $unknown = $action(['login' => 'nobody@example.com']);

        $this->assertSame(ForgotPassword::GENERIC_STATUS, $known);
        $this->assertSame($known, $throttledByTheBroker);
        $this->assertSame($known, $unknown);
        Notification::assertSentToTimes($user, ResetPasswordNotification::class, 1);
    }

    public function test_every_answer_takes_the_time_of_the_timebox(): void
    {
        Notification::fake();
        config(['auth.timebox_duration' => 123456]);
        $timebox = new RecordingTimebox;
        $this->app->instance(Timebox::class, $timebox);

        $user = $this->createUser();
        app(ForgotPassword::class)(['login' => $user->email]);
        app(ForgotPassword::class)(['login' => 'nobody@example.com']);

        $this->assertSame([123456, 123456], $timebox->durations);
    }

    public function test_it_limits_the_attempts_of_an_ip_across_different_logins(): void
    {
        Notification::fake();

        $limit = Guardian::getForgotPasswordFeature()->getMaxAttempts() * ForgotPassword::IP_ATTEMPTS_MULTIPLIER;

        for ($i = 0; $i < $limit; $i++) {
            $this->assertSame(ForgotPassword::GENERIC_STATUS, app(ForgotPassword::class)(['login' => "someone{$i}@example.com"]));
        }

        $this->expectRateLimitedBy(ResetPasswordException::class);

        app(ForgotPassword::class)(['login' => 'one-more@example.com']);
    }

    public function test_the_limit_of_a_login_still_applies_below_the_limit_of_the_ip(): void
    {
        Notification::fake();

        $maxAttempts = Guardian::getForgotPasswordFeature()->getMaxAttempts();

        for ($i = 0; $i < $maxAttempts; $i++) {
            app(ForgotPassword::class)(['login' => 'same@example.com']);
        }

        $this->expectRateLimitedBy(ResetPasswordException::class);

        app(ForgotPassword::class)(['login' => 'same@example.com']);
    }

    protected function withoutThrottling(): array
    {
        return [Fortress::make()->basic()->passwordReset(forgotPasswordMaxAttempts: false)];
    }

    #[WithFortresses('withoutThrottling')]
    public function test_neither_the_login_nor_the_ip_is_limited_when_throttling_is_off(): void
    {
        Notification::fake();

        for ($i = 0; $i < 60; $i++) {
            $this->assertSame(ForgotPassword::GENERIC_STATUS, app(ForgotPassword::class)(['login' => $i % 2 ? 'same@example.com' : "other{$i}@example.com"]));
        }
    }
}
