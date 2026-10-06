<?php

namespace Datalogix\Guardian\Tests\Fixtures;

use Datalogix\Guardian\Contracts\CanManageTwoFactorAuthentication;
use Datalogix\Guardian\Contracts\CanManageTwoFactorRecoveryCodes;
use Datalogix\Guardian\Contracts\TwoFactorAuthenticatable;
use Datalogix\Guardian\Contracts\TwoFactorRecoveryCodeAuthenticatable;
use Datalogix\Guardian\Fortress;

class ContractTwoFactorUser extends User implements CanManageTwoFactorAuthentication, CanManageTwoFactorRecoveryCodes, TwoFactorAuthenticatable, TwoFactorRecoveryCodeAuthenticatable
{
    protected ?string $contractSecret = null;

    protected array $contractRecoveryCodes = [];

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

    public function getTwoFactorRecoveryCodes(Fortress $fortress): array
    {
        return $this->contractRecoveryCodes;
    }

    public function saveTwoFactorRecoveryCodes(Fortress $fortress, array $codes): void
    {
        $this->contractRecoveryCodes = $codes;
    }
}
