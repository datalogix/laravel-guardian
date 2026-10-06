<?php

namespace Datalogix\Guardian\Tests\Feature\Framework\Inertia;

use Datalogix\Guardian\Actions\EnableTwoFactor;
use Datalogix\Guardian\Actions\Login;
use Datalogix\Guardian\Actions\PrepareTwoFactorSetup;
use Datalogix\Guardian\Fortress;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Group;
use PragmaRX\Google2FA\Google2FA;

#[Group('inertia')]
class TwoFactorChallengeControllerTest extends InertiaTestCase
{
    protected function fortresses(): array
    {
        return [Fortress::make()->inertia()->basic()->twoFactor(rememberOnDevice: true, rememberForDays: 14)];
    }

    protected function createUserWithPendingChallenge(): array
    {
        $user = $this->createUser(['password' => Hash::make('secret123')]);

        $this->actingAs($user);
        session()->put('auth.password_confirmed_at', time());
        $setup = app(PrepareTwoFactorSetup::class)($user);
        app(EnableTwoFactor::class)($user, ['code' => app(Google2FA::class)->getCurrentOtp($setup['secret'])]);
        $this->app['auth']->guard()->logout();
        session()->flush();

        app(Login::class)(['login' => $user->email, 'password' => 'secret123']);

        return [$user->fresh(), $setup['secret']];
    }

    public function test_it_redirects_to_login_without_a_pending_challenge(): void
    {
        $this->inertiaGet('/two-factor/challenge')->assertRedirect(url('/login'));
    }

    public function test_it_renders_with_a_pending_challenge(): void
    {
        $this->createUserWithPendingChallenge();

        $this->assertPage($this->inertiaGet('/two-factor/challenge'), 'Guardian/TwoFactorChallenge', [
            'method' => 'totp',
            'canRememberDevice' => true,
            'rememberDeviceDays' => 14,
            'endpoints.submit' => url('/two-factor/challenge'),
            'endpoints.resend' => url('/two-factor/challenge/resend'),
        ]);
    }

    public function test_submit_authenticates_with_a_valid_code(): void
    {
        [$user, $secret] = $this->createUserWithPendingChallenge();

        $this->inertiaPost('/two-factor/challenge', ['code' => app(Google2FA::class)->getCurrentOtp($secret)])
            ->assertRedirect();

        $this->assertAuthenticatedAs($user);
    }

    public function test_submit_rejects_an_invalid_code(): void
    {
        $this->createUserWithPendingChallenge();

        $this->inertiaPost('/two-factor/challenge', ['code' => '000000'])->assertSessionHasErrors();

        $this->assertGuest();
    }

    public function test_resend_goes_back_to_the_page(): void
    {
        $this->createUserWithPendingChallenge();

        $this->from('/two-factor/challenge')
            ->inertiaPost('/two-factor/challenge/resend')
            ->assertRedirect('/two-factor/challenge');
    }
}
