<?php

namespace Datalogix\Guardian\Tests\Feature\Actions;

use Datalogix\Guardian\Actions\Logout;
use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Support\TwoFactor\TwoFactorUser;
use Datalogix\Guardian\Tests\Attributes\WithFortresses;
use Datalogix\Guardian\Tests\TestCase;

class LogoutActionTest extends TestCase
{
    public function test_it_logs_the_user_out(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $this->assertTrue(Guardian::isAuthenticated());

        app(Logout::class)();

        $this->assertFalse(Guardian::isAuthenticated());
    }

    public function test_it_regenerates_the_session_token(): void
    {
        $this->actingAs($this->createUser());

        $originalToken = session()->token();

        app(Logout::class)();

        $this->assertNotSame($originalToken, session()->token());
    }

    protected function rememberingDevices(): array
    {
        return [Fortress::make()->basic()->twoFactor(rememberOnDevice: true)];
    }

    #[WithFortresses('rememberingDevices')]
    public function test_it_keeps_the_trusted_device_for_the_next_login(): void
    {
        $user = $this->createUser();
        app(TwoFactorUser::class)->saveTwoFactorSecret($user, Guardian::getCurrentOrDefaultFortress(), 'JBSWY3DPEHPK3PXP');
        Guardian::rememberTwoFactorOnCurrentDevice($user->fresh());
        $cookie = app('cookie')->queued('guardian_default_remember_2fa');
        app('cookie')->flushQueuedCookies();

        $this->actingAs($user);
        app(Logout::class)();

        // "Remember this device" is for the next logins, so signing out keeps it.
        $this->assertNull(app('cookie')->queued('guardian_default_remember_2fa'));

        $this->app['request']->cookies->set($cookie->getName(), $cookie->getValue());

        $this->assertFalse(Guardian::requiresTwoFactorChallenge($user->fresh()));
    }
}
