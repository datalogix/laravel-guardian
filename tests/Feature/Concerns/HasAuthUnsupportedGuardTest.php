<?php

namespace Datalogix\Guardian\Tests\Feature\Concerns;

use Datalogix\Guardian\Exceptions\UnsupportedAuthGuardException;
use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Tests\Fixtures\BareGuard;
use Datalogix\Guardian\Tests\Fixtures\BareGuardWithProvider;
use Datalogix\Guardian\Tests\Fixtures\BareUserProvider;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Support\Facades\Auth;

class HasAuthUnsupportedGuardTest extends TestCase
{
    protected function bareGuardFortress(): Fortress
    {
        Auth::extend('bare', fn () => new BareGuard);
        config(['auth.guards.bare' => ['driver' => 'bare']]);

        // A standalone, unregistered Fortress: this exercises HasAuth's own
        // exception branches directly, without involving FortressRegistry's
        // boot-time validation (which would otherwise fail to boot the whole
        // application for a guard that can't resolve a user provider).
        return Fortress::make()->guard('bare');
    }

    public function test_auth_with_provider_throws_for_a_guard_without_get_provider(): void
    {
        $this->expectException(UnsupportedAuthGuardException::class);
        $this->expectExceptionMessage(UnsupportedAuthGuardException::forGuard('bare', BareGuard::class)->getMessage());

        $this->bareGuardFortress()->authWithProvider();
    }

    public function test_auth_provider_throws_for_a_guard_without_get_provider(): void
    {
        $this->expectException(UnsupportedAuthGuardException::class);
        $this->expectExceptionMessage(UnsupportedAuthGuardException::forGuard('bare', BareGuard::class)->getMessage());

        $this->bareGuardFortress()->authProvider();
    }

    public function test_auth_model_class_throws_for_a_guard_without_get_provider(): void
    {
        $this->expectException(UnsupportedAuthGuardException::class);
        $this->expectExceptionMessage(UnsupportedAuthGuardException::forGuard('bare', BareGuard::class)->getMessage());

        $this->bareGuardFortress()->authModelClass();
    }

    public function test_auth_model_class_throws_for_a_provider_without_get_model(): void
    {
        Auth::extend('bare-with-provider', fn () => new BareGuardWithProvider);
        config(['auth.guards.bare-with-provider' => ['driver' => 'bare-with-provider']]);

        $fortress = Fortress::make()->guard('bare-with-provider');

        // This guard resolves a provider fine, so authProvider() succeeds;
        // only authModelClass()'s own getModel() check should fail.
        $this->assertNotNull($fortress->authProvider());

        $this->expectException(UnsupportedAuthGuardException::class);
        $this->expectExceptionMessage(UnsupportedAuthGuardException::forProvider('bare-with-provider', BareUserProvider::class)->getMessage());

        $fortress->authModelClass();
    }

    public function test_get_pending_two_factor_challenge_user_is_null_for_an_unsupported_guard(): void
    {
        Auth::extend('bare', fn () => new BareGuard);
        config(['auth.guards.bare' => ['driver' => 'bare']]);

        $fortress = Fortress::make()->basic()->twoFactor()->guard('bare');
        Guardian::setCurrentFortress($fortress);

        // A pending challenge session exists, but resolving the user needs
        // authProvider()->retrieveById(), which this guard cannot supply.
        Guardian::startTwoFactorChallenge($this->createUser());

        $this->assertNull(Guardian::getPendingTwoFactorChallengeUser());
    }
}
