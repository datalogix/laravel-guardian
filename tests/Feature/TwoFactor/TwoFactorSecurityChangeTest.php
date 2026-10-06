<?php

namespace Datalogix\Guardian\Tests\Feature\TwoFactor;

use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Support\TwoFactor\TwoFactorSecurityChange;
use Datalogix\Guardian\Tests\Fixtures\BareGuard;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Support\Facades\Auth;
use stdClass;

class TwoFactorSecurityChangeTest extends TestCase
{
    public function test_it_ends_the_remember_me_cookies_of_the_user(): void
    {
        $user = $this->createUser(['remember_token' => 'the-old-token']);

        app(TwoFactorSecurityChange::class)->apply($user);

        $this->assertNotSame('the-old-token', $user->fresh()->getRememberToken());
    }

    public function test_something_that_is_not_a_user_is_left_alone(): void
    {
        $this->expectNotToPerformAssertions();

        app(TwoFactorSecurityChange::class)->apply(new stdClass);
    }

    public function test_a_guard_without_users_of_its_own_to_remember_is_left_alone(): void
    {
        Auth::extend('bare', fn () => new BareGuard);
        config(['auth.guards.bare' => ['driver' => 'bare']]);
        Guardian::setCurrentFortress(Fortress::make()->basic('bare')->guard('bare'));
        $user = $this->createUser(['remember_token' => 'the-old-token']);

        app(TwoFactorSecurityChange::class)->apply($user);

        $this->assertSame('the-old-token', $user->fresh()->getRememberToken());
    }
}
