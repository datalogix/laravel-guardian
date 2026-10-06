<?php

namespace Datalogix\Guardian\Tests\Feature\Actions;

use Datalogix\Guardian\Actions\CompleteOAuthRegistration;
use Datalogix\Guardian\Actions\DisableTwoFactor;
use Datalogix\Guardian\Actions\DisconnectOAuthIdentity;
use Datalogix\Guardian\Actions\EnableTwoFactor;
use Datalogix\Guardian\Actions\ForgotPassword;
use Datalogix\Guardian\Actions\PrepareTwoFactorSetup;
use Datalogix\Guardian\Actions\RegenerateTwoFactorRecoveryCodes;
use Datalogix\Guardian\Actions\ResetPassword;
use Datalogix\Guardian\Actions\SendEmailVerificationNotification;
use Datalogix\Guardian\Enums\IdentifierKey;
use Datalogix\Guardian\Enums\TwoFactorMethod;
use Datalogix\Guardian\Exceptions\OAuthException;
use Datalogix\Guardian\Exceptions\ResetPasswordException;
use Datalogix\Guardian\Exceptions\TwoFactorChallengeException;
use Datalogix\Guardian\Exceptions\TwoFactorSetupException;
use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Tests\Attributes\WithFortresses;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Group;

/**
 * Every rate-limited action refuses to run once its limit is reached, with the
 * error of its own form. The limiter itself is covered by HasRateLimiterTest.
 */
class ActionLockoutTest extends TestCase
{
    protected function fortresses(): array
    {
        return [Fortress::make()->product()->default()->twoFactor(method: TwoFactorMethod::Email)];
    }

    protected function withOAuth(): array
    {
        return [Fortress::make()->basic()->emailVerification(isRequired: false)->oauth(providers: ['github'])];
    }

    protected function identifiedByCpf(): array
    {
        return [Fortress::make()->basic()->identifierKey(IdentifierKey::CPF)->emailVerification(isRequired: false)->oauth(providers: ['github'])];
    }

    protected function signInWithConfirmedPassword()
    {
        $user = $this->createUser();
        $this->actingAs($user);
        session()->put('auth.password_confirmed_at', time());

        return $user;
    }

    protected function reachTheLimit(): void
    {
        Event::fake([Lockout::class]);

        // Every attempt finds the limit already reached.
        RateLimiter::shouldReceive('hit')->andReturn(PHP_INT_MAX);
        RateLimiter::shouldReceive('availableIn')->andReturn(42);
    }

    protected function assertLockedOut(string $exception, callable $action): void
    {
        try {
            $action();
            $this->fail('The action ran past its limit.');
        } catch (ValidationException $thrown) {
            $this->assertInstanceOf($exception, $thrown);
            $this->assertSame($exception::rateLimited(42)->errors(), $thrown->errors());
            Event::assertDispatched(Lockout::class);
        }
    }

    public function test_forgot_password(): void
    {
        $this->reachTheLimit();

        $this->assertLockedOut(ResetPasswordException::class, fn () => app(ForgotPassword::class)(['login' => 'someone@example.com']));
    }

    public function test_reset_password(): void
    {
        $this->reachTheLimit();

        $this->assertLockedOut(ResetPasswordException::class, fn () => app(ResetPassword::class)([
            'token' => 'any-token',
            'login' => 'someone@example.com',
            'password' => 'NewPassword123!',
        ]));
    }

    public function test_prepare_two_factor_setup(): void
    {
        $user = $this->signInWithConfirmedPassword();
        $this->reachTheLimit();

        $this->assertLockedOut(TwoFactorSetupException::class, fn () => app(PrepareTwoFactorSetup::class)($user));
    }

    public function test_enable_two_factor(): void
    {
        $user = $this->signInWithConfirmedPassword();
        $this->reachTheLimit();

        $this->assertLockedOut(TwoFactorSetupException::class, fn () => app(EnableTwoFactor::class)($user, ['code' => '123456']));
    }

    public function test_disable_two_factor(): void
    {
        $user = $this->signInWithConfirmedPassword();
        $this->reachTheLimit();

        $this->assertLockedOut(TwoFactorSetupException::class, fn () => app(DisableTwoFactor::class)($user));
    }

    public function test_regenerate_two_factor_recovery_codes(): void
    {
        $user = $this->signInWithConfirmedPassword();
        $this->reachTheLimit();

        $this->assertLockedOut(TwoFactorSetupException::class, fn () => app(RegenerateTwoFactorRecoveryCodes::class)($user));
    }

    public function test_resend_two_factor_challenge_code(): void
    {
        Notification::fake();

        $user = $this->signInWithConfirmedPassword();
        $setup = app(PrepareTwoFactorSetup::class)($user, TwoFactorMethod::Email);
        app(EnableTwoFactor::class)($user, ['code' => $this->lastDeliveredCode()]);
        $this->app['auth']->guard()->logout();
        Guardian::startTwoFactorChallenge($user->fresh());

        $this->reachTheLimit();

        $this->assertLockedOut(TwoFactorChallengeException::class, fn () => Guardian::resendPendingTwoFactorChallengeCode());
    }

    #[Group('socialite')]
    #[WithFortresses('withOAuth')]
    public function test_disconnect_oauth_identity(): void
    {
        $user = $this->signInWithConfirmedPassword();
        $this->reachTheLimit();

        $this->assertLockedOut(OAuthException::class, fn () => app(DisconnectOAuthIdentity::class)($user, 'github'));
    }

    #[Group('socialite')]
    #[WithFortresses('identifiedByCpf')]
    public function test_complete_oauth_registration(): void
    {
        Guardian::startPendingOAuthRegistration(
            provider: 'github',
            providerUserId: 'gh-1',
            email: 'pending@example.com',
            name: 'Pending User',
            avatar: null,
        );
        $this->reachTheLimit();

        $this->assertLockedOut(OAuthException::class, fn () => app(CompleteOAuthRegistration::class)(['login' => '529.982.247-25']));
    }

    public function test_send_email_verification_notification_quietly_sends_nothing(): void
    {
        Notification::fake();
        $user = $this->createUser(['email_verified_at' => null]);
        $this->reachTheLimit();

        $this->assertFalse(app(SendEmailVerificationNotification::class)($user));
        Notification::assertNothingSent();
        Event::assertDispatched(Lockout::class);
    }
}
