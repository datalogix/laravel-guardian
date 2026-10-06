<?php

namespace Datalogix\Guardian\Contracts;

use Datalogix\Guardian\Fortress;

interface TwoFactorRecoveryCodeAuthenticatable
{
    /**
     * @return array<int, string> hashed
     */
    public function getTwoFactorRecoveryCodes(Fortress $fortress): array;
}
