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
}
