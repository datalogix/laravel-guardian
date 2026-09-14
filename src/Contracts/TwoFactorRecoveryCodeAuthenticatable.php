<?php

namespace Datalogix\Guardian\Contracts;

use Datalogix\Guardian\Fortress;

interface TwoFactorRecoveryCodeAuthenticatable
{
    public function getTwoFactorRecoveryCodes(Fortress $fortress): array;
}
