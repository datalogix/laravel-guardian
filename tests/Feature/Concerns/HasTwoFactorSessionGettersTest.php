<?php

namespace Datalogix\Guardian\Tests\Feature\Concerns;

use Datalogix\Guardian\Actions\EnableTwoFactor;
use Datalogix\Guardian\Actions\Login;
use Datalogix\Guardian\Actions\PrepareTwoFactorSetup;
use Datalogix\Guardian\Enums\TwoFactorMethod;
use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Notifications\TwoFactorCodeNotification;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

class HasTwoFactorSessionGettersTest extends TestCase
{
    protected function fortresses(): array
    {
        return [Fortress::make()->basic()->twoFactor(method: TwoFactorMethod::Email)];
    }

    protected function enableTwoFactorAndLogin(): array
    {
        $user = $this->createUser(['password' => Hash::make('secret123')]);
        $this->actingAs($user);
        session()->put('auth.password_confirmed_at', time());
        $setup = app(PrepareTwoFactorSetup::class)($user, TwoFactorMethod::Email);
        $code = $this->lastDeliveredCode();
        app(EnableTwoFactor::class)($user, ['code' => $code]);
        $this->app['auth']->guard()->logout();
        session()->flush();

        Notification::fake();
        app(Login::class)(['login' => $user->email, 'password' => 'secret123']);

        return [$user->fresh(), $setup['secret']];
    }

    public function test_challenge_session_getters_reflect_the_pending_challenge(): void
    {
        [$user] = $this->enableTwoFactorAndLogin();

        $this->assertIsArray(Guardian::getTwoFactorChallengeSession());
        $this->assertTrue(Guardian::getTwoFactorChallengeRemember());
        $this->assertSame(TwoFactorMethod::Email, Guardian::getPendingTwoFactorChallengeMethod());
        $this->assertTrue(Guardian::getPendingTwoFactorChallengeUser()->is($user));
    }

    public function test_resend_returns_false_without_a_pending_challenge(): void
    {
        $this->assertFalse(Guardian::resendPendingTwoFactorChallengeCode());
    }

    public function test_resend_dispatches_a_new_code_for_a_pending_email_challenge(): void
    {
        Notification::fake();

        $this->enableTwoFactorAndLogin();

        $this->assertTrue(Guardian::resendPendingTwoFactorChallengeCode());

        Notification::assertSentOnDemand(TwoFactorCodeNotification::class);
    }

    public function test_pending_setup_session_getters(): void
    {
        $user = $this->createUser();

        Guardian::startPendingTwoFactorSetup($user, remember: true, method: TwoFactorMethod::Email);

        $this->assertIsArray(Guardian::getPendingTwoFactorSetupSession());
        $this->assertTrue(Guardian::hasPendingTwoFactorSetup());
        $this->assertTrue(Guardian::getPendingTwoFactorSetupUser()->is($user));
        $this->assertTrue(Guardian::getPendingTwoFactorSetupRemember());
        $this->assertSame(TwoFactorMethod::Email, Guardian::getPendingTwoFactorSetupMethod());
    }

    public function test_complete_pending_two_factor_setup_login_returns_false_without_a_pending_user(): void
    {
        $this->assertFalse(Guardian::completePendingTwoFactorSetupLogin());
    }

    public function test_complete_pending_two_factor_setup_login_finalizes_authentication(): void
    {
        $user = $this->createUser();
        Guardian::startPendingTwoFactorSetup($user, remember: false);

        $this->assertTrue(Guardian::completePendingTwoFactorSetupLogin());
        $this->assertTrue(Guardian::isAuthenticated());
        $this->assertTrue(Guardian::user()->is($user));
    }
}
