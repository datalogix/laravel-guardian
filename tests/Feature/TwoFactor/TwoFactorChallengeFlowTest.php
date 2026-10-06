<?php

namespace Datalogix\Guardian\Tests\Feature\TwoFactor;

use Datalogix\Guardian\Actions\ConfirmTwoFactorChallenge;
use Datalogix\Guardian\Actions\EnableTwoFactor;
use Datalogix\Guardian\Actions\Login;
use Datalogix\Guardian\Actions\PrepareTwoFactorSetup;
use Datalogix\Guardian\Enums\AuthFlowResult;
use Datalogix\Guardian\Enums\IdentifierKey;
use Datalogix\Guardian\Enums\TwoFactorMethod;
use Datalogix\Guardian\Events\TwoFactorChallengeFailed;
use Datalogix\Guardian\Events\TwoFactorChallengeSucceeded;
use Datalogix\Guardian\Events\TwoFactorRecoveryCodeUsed;
use Datalogix\Guardian\Exceptions\TwoFactorChallengeException;
use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Support\TwoFactor\TwoFactorUser;
use Datalogix\Guardian\Tests\Attributes\WithFortresses;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorChallengeFlowTest extends TestCase
{
    protected function fortresses(): array
    {
        return [Fortress::make()->basic()->twoFactor()];
    }

    protected function createUserWithTwoFactor(string $password = 'secret123'): array
    {
        $user = $this->createUser(['password' => Hash::make($password)]);

        $this->actingAs($user);
        session()->put('auth.password_confirmed_at', time());

        $setup = app(PrepareTwoFactorSetup::class)($user);
        $code = app(Google2FA::class)->getCurrentOtp($setup['secret']);
        $recoveryCodes = app(EnableTwoFactor::class)($user, ['code' => $code]);

        $this->app['auth']->guard()->logout();
        session()->flush();

        return [$user->fresh(), $setup['secret'], $recoveryCodes];
    }

    public function test_login_requires_a_two_factor_challenge_for_enabled_users(): void
    {
        [$user] = $this->createUserWithTwoFactor();

        $result = app(Login::class)(['login' => $user->email, 'password' => 'secret123']);

        $this->assertSame(AuthFlowResult::ChallengeRequired, $result);
        $this->assertFalse(Guardian::isAuthenticated());
        $this->assertTrue(Guardian::hasPendingTwoFactorChallenge());
    }

    public function test_valid_totp_code_completes_the_challenge(): void
    {
        Event::fake([TwoFactorChallengeSucceeded::class]);

        [$user, $secret] = $this->createUserWithTwoFactor();
        app(Login::class)(['login' => $user->email, 'password' => 'secret123']);

        $code = app(Google2FA::class)->getCurrentOtp($secret);
        $result = app(ConfirmTwoFactorChallenge::class)(['code' => $code]);

        $this->assertSame(AuthFlowResult::Authenticated, $result);
        $this->assertTrue(Guardian::isAuthenticated());
        $this->assertTrue(Guardian::user()->is($user));
        Event::assertDispatched(TwoFactorChallengeSucceeded::class);
    }

    public function test_a_recovery_code_completes_the_challenge(): void
    {
        Event::fake([TwoFactorRecoveryCodeUsed::class]);

        [$user, , $recoveryCodes] = $this->createUserWithTwoFactor();
        app(Login::class)(['login' => $user->email, 'password' => 'secret123']);

        $result = app(ConfirmTwoFactorChallenge::class)(['code' => $recoveryCodes[0]]);

        $this->assertSame(AuthFlowResult::Authenticated, $result);
        Event::assertDispatched(TwoFactorRecoveryCodeUsed::class);

        // Stored recovery codes are hashed at rest and only ever shown raw once,
        // right after generation, so the remaining count is checked instead.
        $remainingCount = app(TwoFactorUser::class)->getTwoFactorRecoveryCodesCount($user->fresh(), Guardian::getCurrentOrDefaultFortress());
        $this->assertSame(7, $remainingCount);
    }

    public function test_a_used_recovery_code_cannot_be_reused(): void
    {
        [$user, , $recoveryCodes] = $this->createUserWithTwoFactor();
        app(Login::class)(['login' => $user->email, 'password' => 'secret123']);
        app(ConfirmTwoFactorChallenge::class)(['code' => $recoveryCodes[0]]);

        app(Login::class)(['login' => $user->email, 'password' => 'secret123']);

        $this->expectException(TwoFactorChallengeException::class);
        $this->expectExceptionMessage(TwoFactorChallengeException::invalid()->getMessage());

        app(ConfirmTwoFactorChallenge::class)(['code' => $recoveryCodes[0]]);
    }

    public function test_an_invalid_code_fails_the_challenge(): void
    {
        Event::fake([TwoFactorChallengeFailed::class]);

        [$user] = $this->createUserWithTwoFactor();
        app(Login::class)(['login' => $user->email, 'password' => 'secret123']);

        $this->expectException(TwoFactorChallengeException::class);
        $this->expectExceptionMessage(TwoFactorChallengeException::invalid()->getMessage());

        try {
            app(ConfirmTwoFactorChallenge::class)(['code' => '000000']);
        } finally {
            Event::assertDispatched(TwoFactorChallengeFailed::class);
        }
    }

    public function test_confirming_without_a_pending_challenge_throws(): void
    {
        $this->expectException(TwoFactorChallengeException::class);
        $this->expectExceptionMessage(TwoFactorChallengeException::notPending()->getMessage());

        app(ConfirmTwoFactorChallenge::class)(['code' => '123456']);
    }

    public function test_challenge_is_rate_limited(): void
    {
        [$user] = $this->createUserWithTwoFactor();
        $maxAttempts = Guardian::getTwoFactorChallengeFeature()->getMaxAttempts();

        app(Login::class)(['login' => $user->email, 'password' => 'secret123']);

        for ($i = 0; $i < $maxAttempts; $i++) {
            try {
                app(ConfirmTwoFactorChallenge::class)(['code' => '000000']);
            } catch (TwoFactorChallengeException) {
                // expected until the limit is hit
            }
        }

        $this->expectException(TwoFactorChallengeException::class);

        try {
            app(ConfirmTwoFactorChallenge::class)(['code' => '000000']);
        } catch (TwoFactorChallengeException $exception) {
            $this->assertStringContainsString('seconds', $exception->errors()['code'][0]);

            throw $exception;
        }
    }

    protected function identifiedByLoginDeliveringByEmail(): array
    {
        return [Fortress::make()->basic()->identifierKey(IdentifierKey::Login)->twoFactor(method: TwoFactorMethod::Email)];
    }

    #[WithFortresses('identifiedByLoginDeliveringByEmail')]
    public function test_login_surfaces_a_friendly_error_when_the_challenge_code_cannot_be_delivered(): void
    {
        $user = $this->createUser(['password' => Hash::make('secret123'), 'login' => 'jdoe-account']);
        $this->actingAs($user);
        session()->put('auth.password_confirmed_at', time());
        $setup = app(PrepareTwoFactorSetup::class)($user, TwoFactorMethod::Email);
        $code = $this->lastDeliveredCode();
        app(EnableTwoFactor::class)($user, ['code' => $code]);
        $this->app['auth']->guard()->logout();
        session()->flush();

        // No email column value means the challenge code has nowhere to be delivered.
        $user->forceFill(['email' => ''])->saveQuietly();

        $this->expectException(ValidationException::class);

        try {
            app(Login::class)(['login' => 'jdoe-account', 'password' => 'secret123']);
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('login', $exception->errors());

            throw $exception;
        } finally {
            $this->assertFalse(Guardian::hasPendingTwoFactorChallenge());
        }
    }

    public function test_the_challenge_page_redirects_to_login_and_clears_state_for_a_deleted_user(): void
    {
        $user = $this->createUser(['password' => Hash::make('secret123')]);
        $this->actingAs($user);
        session()->put('auth.password_confirmed_at', time());
        $setup = app(PrepareTwoFactorSetup::class)($user);
        $code = app(Google2FA::class)->getCurrentOtp($setup['secret']);
        app(EnableTwoFactor::class)($user, ['code' => $code]);
        $this->app['auth']->guard()->logout();
        session()->flush();

        app(Login::class)(['login' => $user->email, 'password' => 'secret123']);
        $this->assertTrue(Guardian::hasPendingTwoFactorChallenge());

        $user->delete();

        $response = $this->get('/two-factor/challenge');

        $response->assertRedirect();
        $this->assertStringContainsString('/login', $response->headers->get('Location'));
        $this->assertFalse(Guardian::hasPendingTwoFactorChallenge());
    }

    public function test_confirming_a_challenge_for_a_deleted_user_reports_not_pending(): void
    {
        Event::fake([TwoFactorChallengeFailed::class]);

        $user = $this->createUser(['password' => Hash::make('secret123')]);
        $this->actingAs($user);
        session()->put('auth.password_confirmed_at', time());
        $setup = app(PrepareTwoFactorSetup::class)($user);
        $code = app(Google2FA::class)->getCurrentOtp($setup['secret']);
        app(EnableTwoFactor::class)($user, ['code' => $code]);
        $this->app['auth']->guard()->logout();
        session()->flush();

        app(Login::class)(['login' => $user->email, 'password' => 'secret123']);

        $user->delete();

        $this->expectException(TwoFactorChallengeException::class);
        $this->expectExceptionMessage(TwoFactorChallengeException::notPending()->getMessage());

        try {
            app(ConfirmTwoFactorChallenge::class)(['code' => $code]);
        } finally {
            $this->assertFalse(Guardian::hasPendingTwoFactorChallenge());
            Event::assertDispatched(TwoFactorChallengeFailed::class);
        }
    }

    protected function deliveringByEmail(): array
    {
        return [Fortress::make()->basic()->twoFactor(method: TwoFactorMethod::Email)];
    }

    #[WithFortresses('deliveringByEmail')]
    public function test_resend_does_nothing_once_the_secret_is_no_longer_available(): void
    {
        Notification::fake();

        $user = $this->createUser(['password' => Hash::make('secret123')]);
        $this->actingAs($user);
        session()->put('auth.password_confirmed_at', time());
        $setup = app(PrepareTwoFactorSetup::class)($user, TwoFactorMethod::Email);
        $code = $this->lastDeliveredCode();
        app(EnableTwoFactor::class)($user, ['code' => $code]);
        $this->app['auth']->guard()->logout();
        session()->flush();

        app(Login::class)(['login' => $user->email, 'password' => 'secret123']);

        // The pending challenge session still records "email" as its method,
        // but the secret backing it is now gone.
        app(TwoFactorUser::class)->saveTwoFactorSecret($user->fresh(), Guardian::getCurrentOrDefaultFortress(), null);

        Notification::fake();

        $this->assertFalse(Guardian::resendPendingTwoFactorChallengeCode());
        Notification::assertNothingSent();
    }
}
