<?php

namespace Datalogix\Guardian\Tests\Feature\Framework\Inertia;

use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Framework\Inertia\Controllers\TwoFactorChallengeController;
use Datalogix\Guardian\Framework\Inertia\Controllers\TwoFactorSetupController;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Support\TwoFactor\TwoFactorUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Group;
use PragmaRX\Google2FA\Google2FA;

/**
 * Without the route middleware, so the controllers' own guards run.
 */
#[Group('inertia')]
class TwoFactorControllersEdgeCasesTest extends InertiaTestCase
{
    protected function fortresses(): array
    {
        return [Fortress::make()->inertia()->basic()->twoFactor()];
    }

    protected function controller(): TwoFactorSetupController
    {
        return app(TwoFactorSetupController::class);
    }

    protected function signIn()
    {
        $user = $this->createUser(['password' => Hash::make('secret123')]);

        $this->actingAs($user);
        session()->put('auth.password_confirmed_at', time());

        return $user;
    }

    protected function enableTwoFactor(): void
    {
        $this->inertiaPost('/two-factor/setup/prepare');

        $secret = $this->inertiaGet('/two-factor/setup')->json('props.secret');

        $this->inertiaPost('/two-factor/setup/enable', ['code' => app(Google2FA::class)->getCurrentOtp($secret)]);
    }

    protected function letTheConfirmationGoStale(): void
    {
        session()->put('auth.password_confirmed_at', time() - 86400);
    }

    protected function assertSendsToThePasswordConfirmation($response): void
    {
        $response->assertRedirect(url('/confirm-password'));

        $this->assertSame(url('/two-factor/setup'), session('url.intended'));
    }

    protected function assertRedirectsTo(string $url, mixed $response): void
    {
        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame($url, $response->getTargetUrl());
    }

    public function test_the_challenge_sends_a_visitor_without_a_pending_challenge_to_the_login(): void
    {
        $response = app(TwoFactorChallengeController::class)(Request::create('/two-factor/challenge'));

        $this->assertRedirectsTo(url('/login'), $response);
    }

    public function test_the_setup_sends_a_guest_to_the_login(): void
    {
        $response = $this->controller()(Request::create('/two-factor/setup'));

        $this->assertRedirectsTo(url('/login'), $response);
    }

    public function test_continuing_sends_a_guest_to_the_login(): void
    {
        $response = $this->controller()->continueAfterSetup(Request::create('/two-factor/setup/continue', 'POST'));

        $this->assertRedirectsTo(url('/login'), $response);
    }

    public function test_the_actions_leave_everything_as_it_is_when_there_is_nobody_to_act_for(): void
    {
        $controller = $this->controller();

        $this->assertInstanceOf(RedirectResponse::class, $controller->prepare());
        $this->assertInstanceOf(RedirectResponse::class, $controller->enable(Request::create('/', 'POST', ['code' => '123456'])));
        $this->assertInstanceOf(RedirectResponse::class, $controller->disable());
        $this->assertInstanceOf(RedirectResponse::class, $controller->regenerateRecoveryCodes());

        $this->assertNull(Guardian::getTwoFactorSetupSession());
        $this->assertGuest();
    }

    public function test_enabling_asks_for_the_password_again_when_the_confirmation_is_stale(): void
    {
        $user = $this->signIn();
        $this->inertiaPost('/two-factor/setup/prepare');
        $secret = $this->inertiaGet('/two-factor/setup')->json('props.secret');

        $this->letTheConfirmationGoStale();

        $this->assertSendsToThePasswordConfirmation(
            $this->inertiaPost('/two-factor/setup/enable', ['code' => app(Google2FA::class)->getCurrentOtp($secret)])
        );
        $this->assertFalse(app(TwoFactorUser::class)->hasTwoFactorEnabled($user->fresh(), Guardian::getCurrentOrDefaultFortress()));
    }

    public function test_disabling_asks_for_the_password_again_when_the_confirmation_is_stale(): void
    {
        $user = $this->signIn();
        $this->enableTwoFactor();

        $this->letTheConfirmationGoStale();

        $this->assertSendsToThePasswordConfirmation($this->inertiaDelete('/two-factor/setup'));
        $this->assertTrue(app(TwoFactorUser::class)->hasTwoFactorEnabled($user->fresh(), Guardian::getCurrentOrDefaultFortress()));
    }

    public function test_regenerating_the_recovery_codes_asks_for_the_password_again_when_the_confirmation_is_stale(): void
    {
        $this->signIn();
        $this->enableTwoFactor();

        $this->letTheConfirmationGoStale();

        $this->assertSendsToThePasswordConfirmation($this->inertiaPost('/two-factor/setup/recovery-codes'));
        $this->assertNull(session(TwoFactorSetupController::RECOVERY_CODES_FLASH));
    }
}
