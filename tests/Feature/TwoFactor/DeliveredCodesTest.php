<?php

namespace Datalogix\Guardian\Tests\Feature\TwoFactor;

use Datalogix\Guardian\Actions\ConfirmTwoFactorChallenge;
use Datalogix\Guardian\Actions\EnableTwoFactor;
use Datalogix\Guardian\Actions\PrepareTwoFactorSetup;
use Datalogix\Guardian\Enums\AuthFlowResult;
use Datalogix\Guardian\Enums\TwoFactorMethod;
use Datalogix\Guardian\Exceptions\TwoFactorChallengeException;
use Datalogix\Guardian\Exceptions\TwoFactorSetupException;
use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Support\SessionState;
use Datalogix\Guardian\Support\TwoFactor\DeliveredCodes;
use Datalogix\Guardian\Support\TwoFactor\TwoFactorUser;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

class DeliveredCodesTest extends TestCase
{
    protected function fortresses(): array
    {
        return [
            Fortress::make()->basic()->twoFactor(method: TwoFactorMethod::Email),
            Fortress::make()->admin()->twoFactor(method: TwoFactorMethod::Email),
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    protected function userWithEmailTwoFactor()
    {
        $user = $this->createUser();
        $this->actingAs($user);
        session()->put('auth.password_confirmed_at', time());

        app(PrepareTwoFactorSetup::class)($user, TwoFactorMethod::Email);
        app(EnableTwoFactor::class)($user, ['code' => $this->lastDeliveredCode()]);

        $this->app['auth']->guard()->logout();

        return $user->fresh();
    }

    protected function inAChallenge($user): string
    {
        Guardian::startTwoFactorChallenge($user);

        return $this->lastDeliveredCode();
    }

    protected function answer(string $code): AuthFlowResult|ValidationException
    {
        try {
            return app(ConfirmTwoFactorChallenge::class)(['code' => $code]);
        } catch (ValidationException $exception) {
            return $exception;
        }
    }

    protected function wrong(string $code): string
    {
        return str_pad((string) (((int) $code + 1) % 1000000), 6, '0', STR_PAD_LEFT);
    }

    public function test_the_code_that_was_sent_signs_the_user_in(): void
    {
        $code = $this->inAChallenge($this->userWithEmailTwoFactor());

        $this->assertSame(AuthFlowResult::Authenticated, $this->answer($code));
    }

    public function test_a_code_nobody_was_sent_is_refused(): void
    {
        $user = $this->userWithEmailTwoFactor();
        $this->inAChallenge($user);

        // What was accepted before: the code the secret gives 5 minutes from now.
        $secret = app(TwoFactorUser::class)->getTwoFactorSecret($user, Guardian::getCurrentOrDefaultFortress());
        $future = app(Google2FA::class)->oathTotp($secret, (int) floor((time() + 300) / 30));

        $this->assertInstanceOf(TwoFactorChallengeException::class, $this->answer($future));
        $this->assertFalse(Guardian::isAuthenticated());
    }

    public function test_a_code_is_thrown_away_after_a_few_wrong_guesses(): void
    {
        $code = $this->inAChallenge($this->userWithEmailTwoFactor());

        for ($i = 0; $i < DeliveredCodes::MAX_WRONG_ATTEMPTS; $i++) {
            $this->assertInstanceOf(TwoFactorChallengeException::class, $this->answer($this->wrong($code)));
            $this->travel(61)->seconds(); // past the limit per minute
        }

        $this->assertInstanceOf(TwoFactorChallengeException::class, $this->answer($code));
    }

    public function test_only_the_code_sent_last_is_accepted(): void
    {
        $first = $this->inAChallenge($this->userWithEmailTwoFactor());

        Guardian::resendPendingTwoFactorChallengeCode();
        $second = $this->lastDeliveredCode();

        if ($first !== $second) {
            $this->assertInstanceOf(TwoFactorChallengeException::class, $this->answer($first));
        }

        $this->assertSame(AuthFlowResult::Authenticated, $this->answer($second));
    }

    public function test_a_code_expires_with_the_challenge(): void
    {
        $code = $this->inAChallenge($this->userWithEmailTwoFactor());

        $this->travel(Guardian::getTwoFactorChallengeTtl() + 1)->seconds();

        $this->assertInstanceOf(TwoFactorChallengeException::class, $this->answer($code));
    }

    public function test_a_code_can_be_used_once(): void
    {
        $codes = app(DeliveredCodes::class);
        session()->put('step', ['started_at' => now()->timestamp]);
        $code = $codes->issue('step', 600);

        $this->assertTrue($codes->verify('step', $code, 600));
        $this->assertFalse($codes->verify('step', $code, 600));
    }

    public function test_the_session_keeps_no_code_in_the_clear(): void
    {
        $code = $this->inAChallenge($this->userWithEmailTwoFactor());

        $this->assertStringNotContainsString($code, json_encode(session()->all()));
    }

    public function test_enabling_by_email_takes_the_code_that_was_sent_only(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);
        session()->put('auth.password_confirmed_at', time());

        $setup = app(PrepareTwoFactorSetup::class)($user, TwoFactorMethod::Email);
        $computed = app(Google2FA::class)->getCurrentOtp($setup['secret']);

        if ($computed !== $this->lastDeliveredCode()) {
            try {
                app(EnableTwoFactor::class)($user, ['code' => $computed]);
                $this->fail('A code nobody was sent enabled two-factor authentication.');
            } catch (TwoFactorSetupException $exception) {
                $this->assertSame(TwoFactorSetupException::invalidCode()->getMessage(), $exception->getMessage());
            }
        }

        app(EnableTwoFactor::class)($user, ['code' => $this->lastDeliveredCode()]);

        $this->assertTrue(app(TwoFactorUser::class)->hasTwoFactorEnabled($user->fresh(), Guardian::getCurrentOrDefaultFortress()));
    }

    public function test_wrong_codes_are_capped_for_the_day_whatever_the_limit_per_minute(): void
    {
        $user = $this->userWithEmailTwoFactor();

        for ($i = 0; $i < ConfirmTwoFactorChallenge::DAILY_MAX_ATTEMPTS; $i++) {
            // A fresh code every few guesses, as someone with the password could ask for.
            $code = $i % DeliveredCodes::MAX_WRONG_ATTEMPTS === 0 ? $this->inAChallenge($user) : $code;

            $this->assertInstanceOf(TwoFactorChallengeException::class, $this->answer($this->wrong($code)));
            $this->travel(61)->seconds();
        }

        $code = $this->inAChallenge($user);
        $answer = $this->answer($code);

        $this->assertInstanceOf(TwoFactorChallengeException::class, $answer);
        $this->assertStringContainsString('Too many login attempts', $answer->getMessage());
    }

    public function test_a_success_starts_the_day_over(): void
    {
        $user = $this->userWithEmailTwoFactor();

        for ($i = 0; $i < ConfirmTwoFactorChallenge::DAILY_MAX_ATTEMPTS - 1; $i++) {
            $code = $i % DeliveredCodes::MAX_WRONG_ATTEMPTS === 0 ? $this->inAChallenge($user) : $code;
            $this->answer($this->wrong($code));
            $this->travel(61)->seconds();
        }

        $this->assertSame(AuthFlowResult::Authenticated, $this->answer($this->inAChallenge($user)));
        $this->app['auth']->guard()->logout();

        // Past the limit on sending codes, which is not what this is about.
        $this->travel(301)->seconds();
        $code = $this->inAChallenge($user);

        // One more wrong code would have been the last of the day, but the day starts over.
        $this->assertInstanceOf(TwoFactorChallengeException::class, $this->answer($this->wrong($code)));
        $this->assertNotInstanceOf(ValidationException::class, $this->answer($this->inAChallenge($user)));
    }

    public function test_the_attempts_of_a_user_do_not_count_against_the_user_of_another_fortress_with_the_same_id(): void
    {
        $user = $this->userWithEmailTwoFactor();
        $code = $this->inAChallenge($user);

        for ($i = 0; $i < Guardian::getTwoFactorChallengeFeature()->getMaxAttempts(); $i++) {
            $this->answer($this->wrong($code));
        }

        $this->assertStringContainsString('Too many login attempts', $this->answer($code)->getMessage());

        // The same ID in the admin fortress is someone else.
        Guardian::setCurrentFortress(Guardian::getFortress('admin'));
        $code = $this->inAChallenge($user);

        $this->assertSame(AuthFlowResult::Authenticated, $this->answer($code));
    }

    public function test_no_code_is_sent_once_the_challenge_is_gone(): void
    {
        $user = $this->userWithEmailTwoFactor();
        $this->inAChallenge($user);
        Notification::fake();

        // The challenge ends between the check for it and the code being issued.
        $this->app->instance(DeliveredCodes::class, new class(app(SessionState::class)) extends DeliveredCodes
        {
            public function issue(string $sessionKey, int|false|null $ttl): ?string
            {
                return null;
            }
        });

        $this->assertFalse(Guardian::resendPendingTwoFactorChallengeCode());
        Notification::assertNothingSent();
    }

    public function test_guesses_sent_at_the_same_time_are_held_to_the_limit(): void
    {
        $codes = app(DeliveredCodes::class);
        session()->put('step', ['started_at' => now()->timestamp]);
        $code = $codes->issue('step', 600);
        $before = session('step');

        // Every guess reads the session as it was before the others wrote, like concurrent requests.
        foreach (range(1, 20) as $guess) {
            session()->put('step', $before);
            $codes->verify('step', $this->wrong($code), 600);
        }

        session()->put('step', $before);
        $this->assertFalse($codes->verify('step', $code, 600));
    }

    public function test_the_code_sent_twice_at_the_same_time_is_accepted_once(): void
    {
        $codes = app(DeliveredCodes::class);
        session()->put('step', ['started_at' => now()->timestamp]);
        $code = $codes->issue('step', 600);
        $before = session('step');

        $this->assertTrue($codes->verify('step', $code, 600));

        // The second request read the session before the first one threw the code away.
        session()->put('step', $before);

        $this->assertFalse($codes->verify('step', $code, 600));
    }
}
