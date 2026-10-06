<?php

namespace Datalogix\Guardian\Tests\Feature\Commands;

use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Support\TwoFactor\TrustedDevices;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Support\Facades\DB;

class PruneTrustedDevicesCommandTest extends TestCase
{
    public function test_it_prunes_expired_devices_and_reports_the_count(): void
    {
        $trustedDevices = new TrustedDevices;
        $fortress = Guardian::getCurrentOrDefaultFortress();
        $user = $this->createUser();

        $issued = $trustedDevices->issue($fortress, $user, 30);
        DB::table('two_factor_trusted_devices')->where('id', $issued['id'])->update(['expires_at' => now()->subDay()]);

        $this->artisan('guardian:prune-trusted-devices')
            ->expectsOutputToContain('Pruned 1 trusted device record(s).')
            ->assertSuccessful();

        $this->assertDatabaseMissing('two_factor_trusted_devices', ['id' => $issued['id']]);
    }

    public function test_the_days_option_controls_the_retention_window(): void
    {
        $trustedDevices = new TrustedDevices;
        $fortress = Guardian::getCurrentOrDefaultFortress();
        $user = $this->createUser();

        $issued = $trustedDevices->issue($fortress, $user, 30);
        $trustedDevices->revoke($issued['id'], $fortress, $user);
        DB::table('two_factor_trusted_devices')->where('id', $issued['id'])->update(['revoked_at' => now()->subDays(10)]);

        $this->artisan('guardian:prune-trusted-devices', ['--days' => 5])->assertSuccessful();

        $this->assertDatabaseMissing('two_factor_trusted_devices', ['id' => $issued['id']]);
    }

    public function test_the_retention_window_defaults_to_the_config(): void
    {
        config(['guardian.prune_trusted_devices.days' => 5]);

        $trustedDevices = new TrustedDevices;
        $fortress = Guardian::getCurrentOrDefaultFortress();
        $user = $this->createUser();

        $old = $trustedDevices->issue($fortress, $user, 30);
        $recent = $trustedDevices->issue($fortress, $user, 30);
        $trustedDevices->revokeAll($fortress, $user);
        DB::table('two_factor_trusted_devices')->where('id', $old['id'])->update(['revoked_at' => now()->subDays(10)]);
        DB::table('two_factor_trusted_devices')->where('id', $recent['id'])->update(['revoked_at' => now()->subDays(2)]);

        $this->artisan('guardian:prune-trusted-devices')->assertSuccessful();

        $this->assertDatabaseMissing('two_factor_trusted_devices', ['id' => $old['id']]);
        $this->assertDatabaseHas('two_factor_trusted_devices', ['id' => $recent['id']]);
    }
}
