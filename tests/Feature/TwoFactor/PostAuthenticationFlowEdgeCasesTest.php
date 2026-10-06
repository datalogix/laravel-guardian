<?php

namespace Datalogix\Guardian\Tests\Feature\TwoFactor;

use Datalogix\Guardian\Actions\EnableTwoFactor;
use Datalogix\Guardian\Actions\Login;
use Datalogix\Guardian\Actions\PrepareTwoFactorSetup;
use Datalogix\Guardian\Enums\TwoFactorMethod;
use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Tests\Attributes\WithFortresses;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class PostAuthenticationFlowEdgeCasesTest extends TestCase
{
    protected function fortresses(): array
    {
        return [Fortress::make()->basic()->twoFactor(method: TwoFactorMethod::Email, challengeResendMaxAttempts: 1)];
    }

    public function test_login_surfaces_a_friendly_error_when_the_challenge_start_is_rate_limited(): void
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

        // The first login uses the one allowed send.
        app(Login::class)(['login' => $user->email, 'password' => 'secret123']);

        Guardian::clearTwoFactorChallenge();

        $this->expectException(ValidationException::class);

        try {
            app(Login::class)(['login' => $user->email, 'password' => 'secret123']);
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('login', $exception->errors());

            throw $exception;
        }
    }

    protected function requiringSetupWithoutChallenge(): array
    {
        // Challenge disabled: the unreadable secret only surfaces from the setup check.
        return [Fortress::make()->basic()->twoFactor(challengeRouteAction: false, requireSetupOnLogin: true)];
    }

    #[WithFortresses('requiringSetupWithoutChallenge')]
    public function test_login_reports_a_friendly_error_when_the_stored_secret_is_unreadable(): void
    {
        $user = $this->createUser(['password' => Hash::make('secret123')]);
        DB::table('users')->where('id', $user->id)->update(['two_factor_secret' => 'not-encrypted-data']);

        $this->expectException(ValidationException::class);

        try {
            app(Login::class)(['login' => $user->email, 'password' => 'secret123']);
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('could not be verified', $exception->errors()['login'][0]);

            throw $exception;
        }
    }
}
