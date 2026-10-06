<?php

namespace Datalogix\Guardian\Tests\Feature\TwoFactor;

use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Support\TwoFactor\TrustedDevices;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;

class TrustedDevicesTest extends TestCase
{
    protected TrustedDevices $trustedDevices;

    protected function setUp(): void
    {
        parent::setUp();

        $this->trustedDevices = new TrustedDevices;
    }

    protected function fortress()
    {
        return Guardian::getCurrentOrDefaultFortress();
    }

    public function test_is_available_when_the_table_exists(): void
    {
        $this->assertTrue($this->trustedDevices->isAvailable());
    }

    public function test_issue_without_a_name_falls_back_to_guessing_the_device(): void
    {
        // Testbench's default test Request carries a "Symfony" User-Agent that
        // matches none of guessDeviceName()'s browser/platform patterns, which
        // also resolves to a blank name — but for a different reason than an
        // actually-missing header, so it's cleared here to exercise that branch.
        $this->app['request']->headers->remove('User-Agent');

        $issued = $this->trustedDevices->issue($this->fortress(), $this->createUser(), 30);

        $this->assertDatabaseHas('two_factor_trusted_devices', ['id' => $issued['id'], 'name' => null]);
    }

    public function test_issue_without_a_name_guesses_a_recognizable_device(): void
    {
        $this->app['request']->headers->set(
            'User-Agent',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36'
        );

        $issued = $this->trustedDevices->issue($this->fortress(), $this->createUser(), 30);

        $this->assertDatabaseHas('two_factor_trusted_devices', ['id' => $issued['id'], 'name' => 'Chrome on Windows']);
    }

    public static function userAgents(): array
    {
        return [
            'Edge on Windows' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36 Edg/120.0', 'Edge on Windows'],
            'Opera on Linux' => ['Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36 OPR/106.0', 'Opera on Linux'],
            'Chrome on iPhone' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/120.0 Mobile/15E148 Safari/604.1', 'Chrome on iPhone'],
            'Firefox on Android' => ['Mozilla/5.0 (Android 14; Mobile; rv:121.0) Gecko/121.0 Firefox/121.0', 'Firefox on Android'],
            'Safari on iPad' => ['Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1', 'Safari on iPad'],
            'Safari on macOS' => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 14_0) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15', 'Safari on macOS'],
            'only the browser' => ['Mozilla/5.0 (X11; FreeBSD amd64; rv:121.0) Gecko/20100101 Firefox/121.0', 'Firefox'],
            'only the platform' => ['SomeBot/1.0 (Windows NT 10.0)', 'Windows'],
        ];
    }

    #[DataProvider('userAgents')]
    public function test_issue_without_a_name_names_the_device_after_its_browser_and_platform(string $userAgent, string $name): void
    {
        $this->app['request']->headers->set('User-Agent', $userAgent);

        $issued = $this->trustedDevices->issue($this->fortress(), $this->createUser(), 30);

        $this->assertDatabaseHas('two_factor_trusted_devices', ['id' => $issued['id'], 'name' => $name]);
    }

    public function test_issue_without_a_name_leaves_it_empty_for_an_unrecognizable_device(): void
    {
        // Testbench's default request carries a "Symfony" User-Agent, which names no
        // known browser or platform.
        $issued = $this->trustedDevices->issue($this->fortress(), $this->createUser(), 30);

        $this->assertDatabaseHas('two_factor_trusted_devices', ['id' => $issued['id'], 'name' => null]);
    }

    public function test_touch_if_valid_returns_false_for_an_unknown_device_id(): void
    {
        $user = $this->createUser();

        $this->assertFalse($this->trustedDevices->touchIfValid($this->fortress(), $user, 999999, 'any-token'));
    }

    public function test_issue_creates_a_device_and_returns_its_token(): void
    {
        $user = $this->createUser();

        $issued = $this->trustedDevices->issue($this->fortress(), $user, 30, 'My Device');

        $this->assertIsArray($issued);
        $this->assertArrayHasKey('id', $issued);
        $this->assertArrayHasKey('token', $issued);
        $this->assertDatabaseHas('two_factor_trusted_devices', [
            'id' => $issued['id'],
            'name' => 'My Device',
            'authenticatable_id' => (string) $user->id,
        ]);
    }

    public function test_touch_if_valid_accepts_the_correct_token(): void
    {
        $user = $this->createUser();
        $issued = $this->trustedDevices->issue($this->fortress(), $user, 30);

        $this->assertTrue($this->trustedDevices->touchIfValid($this->fortress(), $user, $issued['id'], $issued['token']));
    }

    public function test_touch_if_valid_rejects_a_wrong_token(): void
    {
        $user = $this->createUser();
        $issued = $this->trustedDevices->issue($this->fortress(), $user, 30);

        $this->assertFalse($this->trustedDevices->touchIfValid($this->fortress(), $user, $issued['id'], 'wrong-token'));
    }

    public function test_touch_if_valid_rejects_an_expired_device_and_revokes_it(): void
    {
        $user = $this->createUser();
        $issued = $this->trustedDevices->issue($this->fortress(), $user, 30);

        DB::table('two_factor_trusted_devices')
            ->where('id', $issued['id'])
            ->update(['expires_at' => now()->subDay()]);

        $this->assertFalse($this->trustedDevices->touchIfValid($this->fortress(), $user, $issued['id'], $issued['token']));

        $revokedAt = DB::table('two_factor_trusted_devices')
            ->where('id', $issued['id'])
            ->value('revoked_at');

        $this->assertNotNull($revokedAt);
    }

    public function test_list_returns_only_active_devices_for_the_user(): void
    {
        $user = $this->createUser();
        $otherUser = $this->createUser();

        $active = $this->trustedDevices->issue($this->fortress(), $user, 30, 'Active');
        $this->trustedDevices->issue($this->fortress(), $otherUser, 30, 'Someone else');

        $devices = $this->trustedDevices->list($this->fortress(), $user);

        $this->assertCount(1, $devices);
        $this->assertSame('Active', $devices[0]['name']);
        $this->assertSame($active['id'], $devices[0]['id']);
    }

    public function test_list_excludes_revoked_devices(): void
    {
        $user = $this->createUser();
        $issued = $this->trustedDevices->issue($this->fortress(), $user, 30);

        $this->trustedDevices->revoke($issued['id'], $this->fortress(), $user);

        $this->assertCount(0, $this->trustedDevices->list($this->fortress(), $user));
    }

    public function test_revoke_returns_false_for_an_unknown_device(): void
    {
        $user = $this->createUser();

        $this->assertFalse($this->trustedDevices->revoke(999999, $this->fortress(), $user));
    }

    public function test_revoke_all_revokes_every_device_for_the_user(): void
    {
        $user = $this->createUser();
        $this->trustedDevices->issue($this->fortress(), $user, 30);
        $this->trustedDevices->issue($this->fortress(), $user, 30);

        $count = $this->trustedDevices->revokeAll($this->fortress(), $user);

        $this->assertSame(2, $count);
        $this->assertCount(0, $this->trustedDevices->list($this->fortress(), $user));
    }

    public function test_prune_deletes_expired_and_long_revoked_devices(): void
    {
        $user = $this->createUser();

        $expired = $this->trustedDevices->issue($this->fortress(), $user, 30);
        DB::table('two_factor_trusted_devices')
            ->where('id', $expired['id'])
            ->update(['expires_at' => now()->subDay()]);

        $recentlyRevoked = $this->trustedDevices->issue($this->fortress(), $user, 30);
        $this->trustedDevices->revoke($recentlyRevoked['id'], $this->fortress(), $user);

        $longRevoked = $this->trustedDevices->issue($this->fortress(), $user, 30);
        DB::table('two_factor_trusted_devices')
            ->where('id', $longRevoked['id'])
            ->update(['revoked_at' => now()->subDays(60)]);

        $deleted = $this->trustedDevices->prune(30);

        $this->assertSame(2, $deleted);
        $this->assertDatabaseHas('two_factor_trusted_devices', ['id' => $recentlyRevoked['id']]);
        $this->assertDatabaseMissing('two_factor_trusted_devices', ['id' => $expired['id']]);
        $this->assertDatabaseMissing('two_factor_trusted_devices', ['id' => $longRevoked['id']]);
    }
}
