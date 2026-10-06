<?php

namespace Datalogix\Guardian\Tests\Feature\TwoFactor;

use Datalogix\Guardian\Actions\ConfirmTwoFactorChallenge;
use Datalogix\Guardian\Actions\EnableTwoFactor;
use Datalogix\Guardian\Actions\Login;
use Datalogix\Guardian\Actions\PrepareTwoFactorSetup;
use Datalogix\Guardian\Enums\AuthFlowResult;
use Datalogix\Guardian\Events\TwoFactorTrustedDeviceRemembered;
use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorTrustedDeviceChallengeTest extends TestCase
{
    protected function fortresses(): array
    {
        return [Fortress::make()->basic()->twoFactor(rememberOnDevice: true, rememberForDays: 30)];
    }

    protected function enableTwoFactor(): array
    {
        $user = $this->createUser(['password' => Hash::make('secret123')]);

        $this->actingAs($user);
        session()->put('auth.password_confirmed_at', time());

        $setup = app(PrepareTwoFactorSetup::class)($user);
        $code = app(Google2FA::class)->getCurrentOtp($setup['secret']);
        app(EnableTwoFactor::class)($user, ['code' => $code]);

        $this->app['auth']->guard()->logout();
        session()->flush();

        return [$user->fresh(), $setup['secret']];
    }

    public function test_confirming_with_remember_device_issues_a_trusted_device_cookie(): void
    {
        Event::fake([TwoFactorTrustedDeviceRemembered::class]);

        [$user, $secret] = $this->enableTwoFactor();
        app(Login::class)(['login' => $user->email, 'password' => 'secret123']);

        $code = app(Google2FA::class)->getCurrentOtp($secret);
        app(ConfirmTwoFactorChallenge::class)(['code' => $code, 'remember_device' => true]);

        $cookieNames = collect(app('cookie')->getQueuedCookies())->map->getName();
        $this->assertTrue($cookieNames->contains(fn ($name) => str_contains($name, 'remember_2fa')));

        Event::assertDispatched(TwoFactorTrustedDeviceRemembered::class);
        $this->assertDatabaseCount('two_factor_trusted_devices', 1);
    }

    public function test_a_trusted_device_cookie_skips_the_challenge_on_the_next_login(): void
    {
        [$user, $secret] = $this->enableTwoFactor();
        app(Login::class)(['login' => $user->email, 'password' => 'secret123']);

        $code = app(Google2FA::class)->getCurrentOtp($secret);
        app(ConfirmTwoFactorChallenge::class)(['code' => $code, 'remember_device' => true]);

        $queued = collect(app('cookie')->getQueuedCookies())->first(fn ($cookie) => str_contains($cookie->getName(), 'remember_2fa'));
        $this->assertNotNull($queued);

        $this->app['auth']->guard()->logout();
        session()->flush();

        // With no cookie on the request, the challenge is required again.
        $this->assertSame(
            AuthFlowResult::ChallengeRequired,
            app(Login::class)(['login' => $user->email, 'password' => 'secret123'])
        );

        Guardian::clearTwoFactorChallenge();
        $this->app['auth']->guard()->logout();
        session()->flush();

        // Simulating the browser sending the remember-device cookie back skips it.
        $this->app['request']->cookies->set($queued->getName(), $queued->getValue());

        $this->assertSame(
            AuthFlowResult::Authenticated,
            app(Login::class)(['login' => $user->email, 'password' => 'secret123'])
        );
    }
}
