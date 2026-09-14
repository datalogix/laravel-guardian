<?php

namespace Datalogix\Guardian\Contracts;

use Datalogix\Guardian\Fortress;

interface CanManageTwoFactorRecoveryCodes
{
    public function saveTwoFactorRecoveryCodes(Fortress $fortress, array $codes): void;
}
