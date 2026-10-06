<?php

namespace Datalogix\Guardian\Tests\Feature\TwoFactor;

use Datalogix\Guardian\Exceptions\TwoFactorSetupException;
use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Support\TwoFactor\Totp;
use Datalogix\Guardian\Support\TwoFactor\TwoFactorLifecycleManager;
use Datalogix\Guardian\Support\TwoFactor\TwoFactorSetupManager;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorManagersUnstorableUserTest extends TestCase
{
    protected function fortresses(): array
    {
        return [Fortress::make()->basic()->twoFactor()];
    }

    protected function unstorableUser(): Model
    {
        return new class extends Model
        {
            protected $table = 'oauth_identities';

            protected $guarded = [];
        };
    }

    public function test_regenerate_recovery_codes_returns_empty_when_they_cannot_be_stored(): void
    {
        $codes = app(TwoFactorLifecycleManager::class)->regenerateRecoveryCodes($this->unstorableUser());

        $this->assertSame([], $codes);
    }

    public function test_enable_from_pending_setup_throws_when_the_secret_cannot_be_stored(): void
    {
        $secret = app(Totp::class)->generateSecret();
        Guardian::startTwoFactorSetup($secret);
        $code = app(Google2FA::class)->getCurrentOtp($secret);

        $this->expectException(TwoFactorSetupException::class);
        $this->expectExceptionMessage(TwoFactorSetupException::unableToStoreSecret()->getMessage());

        try {
            app(TwoFactorSetupManager::class)->enableFromPendingSetup($this->unstorableUser(), $code);
        } finally {
            $this->assertNull(Guardian::getTwoFactorSetupSession());
        }
    }
}
