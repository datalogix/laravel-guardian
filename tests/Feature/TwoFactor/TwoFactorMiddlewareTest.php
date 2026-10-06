<?php

namespace Datalogix\Guardian\Tests\Feature\TwoFactor;

use Datalogix\Guardian\Actions\EnableTwoFactor;
use Datalogix\Guardian\Actions\Login;
use Datalogix\Guardian\Actions\PrepareTwoFactorSetup;
use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorMiddlewareTest extends TestCase
{
    protected function fortresses(): array
    {
        return [Fortress::make()->basic()->twoFactor()];
    }

    public function test_challenge_page_redirects_to_login_without_a_pending_challenge(): void
    {
        $response = $this->get('/two-factor/challenge');

        $response->assertRedirect();
        $this->assertStringContainsString('/login', $response->headers->get('Location'));
    }

    public function test_challenge_page_is_accessible_with_a_pending_challenge(): void
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

        $response = $this->get('/two-factor/challenge');

        $response->assertOk();
    }

    public function test_setup_page_redirects_to_login_for_guests_without_a_pending_setup(): void
    {
        $response = $this->get('/two-factor/setup');

        $response->assertRedirect();
        $this->assertStringContainsString('/login', $response->headers->get('Location'));
    }

    public function test_setup_page_is_accessible_to_authenticated_users(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $response = $this->get('/two-factor/setup');

        $response->assertOk();
    }
}
