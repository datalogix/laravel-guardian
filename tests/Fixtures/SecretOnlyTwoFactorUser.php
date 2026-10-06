<?php

namespace Datalogix\Guardian\Tests\Fixtures;

use Datalogix\Guardian\Contracts\CanManageTwoFactorAuthentication;
use Datalogix\Guardian\Contracts\TwoFactorAuthenticatable;
use Datalogix\Guardian\Fortress;

/**
 * Manages its secret through the contracts, but cannot store recovery codes.
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
