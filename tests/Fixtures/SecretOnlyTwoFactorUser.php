<?php

namespace Datalogix\Guardian\Tests\Fixtures;

use Datalogix\Guardian\Contracts\CanManageTwoFactorAuthentication;
use Datalogix\Guardian\Contracts\TwoFactorAuthenticatable;
use Datalogix\Guardian\Fortress;

/**
 * Manages its two-factor secret via the storage contracts (so it is
 * "enabled"), but implements neither the recovery-code contract nor points at
 * a table with a two_factor_recovery_codes column — canStoreTwoFactorRecoveryCodes()
 * must be false, exercising TwoFactorSetup's "cannot manage recovery codes" branch.
 */
class SecretOnlyTwoFactorUser extends User implements CanManageTwoFactorAuthentication, TwoFactorAuthenticatable
{
    protected $table = 'oauth_identities';

    protected ?string $contractSecret = 'a-secret-value';

    public function hasTwoFactorEnabled(Fortress $fortress): bool
    {
        return filled($this->contractSecret);
    }

    public function getTwoFactorSecret(Fortress $fortress): ?string
    {
        return $this->contractSecret;
    }

    public function saveTwoFactorSecret(Fortress $fortress, ?string $secret): void
    {
        $this->contractSecret = $secret;
    }
}
