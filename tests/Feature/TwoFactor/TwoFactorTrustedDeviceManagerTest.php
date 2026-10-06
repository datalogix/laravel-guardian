<?php

namespace Datalogix\Guardian\Tests\Feature\TwoFactor;

use Datalogix\Guardian\Events\TwoFactorTrustedDeviceRemembered;
use Datalogix\Guardian\Events\TwoFactorTrustedDeviceRevoked;
use Datalogix\Guardian\Events\TwoFactorTrustedDevicesRevokedAll;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Support\TwoFactor\TwoFactorTrustedDeviceManager;
use Datalogix\Guardian\Support\TwoFactor\TwoFactorUser;
use Datalogix\Guardian\Tests\Fixtures\NonModelTwoFactorUser;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;

class TwoFactorTrustedDeviceManagerTest extends TestCase
{
    protected TwoFactorTrustedDeviceManager $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = app(TwoFactorTrustedDeviceManager::class);
    }

    protected function fortress()
    {
        return Guardian::getCurrentOrDefaultFortress();
    }

    public function test_remember_does_nothing_when_disabled(): void
    {
        $user = $this->createUser();

        $this->manager->remember($this->fortress(), $user, enabled: false, days: 30, cookieName: 'remember_2fa');

        $this->assertEmpty(app('cookie')->getQueuedCookies());
    }

    public static function secureCookieSettings(): array
    {
        return [
            'forced on by the session config' => [true, 'http://localhost', true],
            'forced off by the session config' => [false, 'https://localhost', false],
            'following an https request' => [null, 'https://localhost', true],
            'following an http request' => [null, 'http://localhost', false],
        ];
    }

    #[DataProvider('secureCookieSettings')]
    public function test_remember_marks_the_cookie_secure_like_the_session(?bool $sessionSecure, string $url, bool $secure): void
    {
        config(['session.secure' => $sessionSecure]);
        $this->app->instance('request', Request::create($url));

        $user = $this->createUser();
        app(TwoFactorUser::class)->saveTwoFactorSecret($user, $this->fortress(), 'a-secret');

        $this->manager->remember($this->fortress(), $user, enabled: true, days: 30, cookieName: 'remember_2fa');

        $this->assertSame($secure, app('cookie')->queued('remember_2fa')->isSecure());
    }

    public static function sameSiteSettings(): array
    {
        return [
            'lax' => ['lax'],
            'strict' => ['strict'],
        ];
    }

    #[DataProvider('sameSiteSettings')]
    public function test_remember_gives_the_cookie_the_same_site_of_the_session(string $sameSite): void
    {
        // The cookie jar takes its defaults from the session config when it is built.
        config(['session.same_site' => $sameSite]);
        $this->app->forgetInstance('cookie');
        Cookie::clearResolvedInstance('cookie');

        $user = $this->createUser();
        app(TwoFactorUser::class)->saveTwoFactorSecret($user, $this->fortress(), 'a-secret');

        $this->manager->remember($this->fortress(), $user, enabled: true, days: 30, cookieName: 'remember_2fa');

        $this->assertSame($sameSite, app('cookie')->queued('remember_2fa')->getSameSite());
    }

    public function test_remember_does_nothing_without_a_two_factor_secret(): void
    {
        $user = $this->createUser();

        $this->manager->remember($this->fortress(), $user, enabled: true, days: 30, cookieName: 'remember_2fa');

        $this->assertEmpty(app('cookie')->getQueuedCookies());
    }

    public function test_remember_does_nothing_when_the_trusted_devices_table_is_unavailable(): void
    {
        $user = $this->createUser();
        app(TwoFactorUser::class)->saveTwoFactorSecret($user, $this->fortress(), 'a-secret');

        Schema::dropIfExists('two_factor_trusted_devices');

        $this->manager->remember($this->fortress(), $user->fresh(), enabled: true, days: 30, cookieName: 'remember_2fa');

        $this->assertEmpty(app('cookie')->getQueuedCookies());
    }

    public function test_should_skip_challenge_forgets_a_malformed_cookie(): void
    {
        $user = $this->createUser();
        $this->app['request']->cookies->set('remember_2fa', 'malformed-without-a-pipe');

        $this->assertFalse($this->manager->shouldSkipChallenge($this->fortress(), $user, true, 'remember_2fa'));
    }

    public function test_should_skip_challenge_forgets_a_cookie_with_a_non_digit_device_id(): void
    {
        $user = $this->createUser();
        $this->app['request']->cookies->set('remember_2fa', 'not-a-number|some-token');

        $this->assertFalse($this->manager->shouldSkipChallenge($this->fortress(), $user, true, 'remember_2fa'));
    }

    public function test_should_skip_challenge_forgets_a_cookie_with_a_blank_token(): void
    {
        $user = $this->createUser();
        $this->app['request']->cookies->set('remember_2fa', '123|');

        $this->assertFalse($this->manager->shouldSkipChallenge($this->fortress(), $user, true, 'remember_2fa'));
    }

    public function test_should_skip_challenge_forgets_an_invalid_device(): void
    {
        $user = $this->createUser();
        $this->app['request']->cookies->set('remember_2fa', '123|wrong-token');

        $this->assertFalse($this->manager->shouldSkipChallenge($this->fortress(), $user, true, 'remember_2fa'));
    }

    protected function userWithTwoFactor()
    {
        $user = $this->createUser();
        app(TwoFactorUser::class)->saveTwoFactorSecret($user, $this->fortress(), 'a-secret');

        return $user;
    }

    protected function rememberDevice(object $user): int
    {
        $this->manager->remember($this->fortress(), $user, enabled: true, days: 30, cookieName: 'remember_2fa');

        return (int) explode('|', app('cookie')->queued('remember_2fa')->getValue())[0];
    }

    public function test_remembering_a_device_announces_it(): void
    {
        Event::fake([TwoFactorTrustedDeviceRemembered::class]);
        $user = $this->userWithTwoFactor();

        $deviceId = $this->rememberDevice($user);

        Event::assertDispatched(TwoFactorTrustedDeviceRemembered::class, fn ($event) => $event->user->is($user) && $event->deviceId === $deviceId);
    }

    public function test_revoking_a_device_announces_which_one(): void
    {
        Event::fake([TwoFactorTrustedDeviceRevoked::class]);
        $user = $this->userWithTwoFactor();
        $deviceId = $this->rememberDevice($user);

        $this->assertFalse($this->manager->revoke($this->fortress(), $user, $deviceId + 1));
        Event::assertNotDispatched(TwoFactorTrustedDeviceRevoked::class);

        $this->assertTrue($this->manager->revoke($this->fortress(), $user, $deviceId));
        Event::assertDispatched(TwoFactorTrustedDeviceRevoked::class, fn ($event) => $event->user->is($user) && $event->deviceId === $deviceId);
    }

    public function test_revoking_every_device_announces_how_many(): void
    {
        Event::fake([TwoFactorTrustedDevicesRevokedAll::class]);
        $user = $this->userWithTwoFactor();
        $this->rememberDevice($user);
        $this->rememberDevice($user);

        $this->assertSame(2, $this->manager->revokeAll($this->fortress(), $user));
        Event::assertDispatchedTimes(TwoFactorTrustedDevicesRevokedAll::class, 1);
        Event::assertDispatched(TwoFactorTrustedDevicesRevokedAll::class, fn ($event) => $event->user->is($user) && $event->count === 2);

        $this->assertSame(0, $this->manager->revokeAll($this->fortress(), $user));
        Event::assertDispatchedTimes(TwoFactorTrustedDevicesRevokedAll::class, 1);
    }

    public function test_a_user_that_is_not_an_eloquent_model_can_have_trusted_devices(): void
    {
        // The events carry Eloquent users, so there is none for such a user.
        Event::fake([TwoFactorTrustedDeviceRemembered::class, TwoFactorTrustedDeviceRevoked::class, TwoFactorTrustedDevicesRevokedAll::class]);
        $user = new NonModelTwoFactorUser;

        $deviceId = $this->rememberDevice($user);
        $this->rememberDevice($user);

        $this->assertTrue($this->manager->revoke($this->fortress(), $user, $deviceId));
        $this->assertSame(1, $this->manager->revokeAll($this->fortress(), $user));
        Event::assertNothingDispatched();
    }
}
