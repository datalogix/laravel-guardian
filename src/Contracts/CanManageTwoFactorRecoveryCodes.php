<?php

namespace Datalogix\Guardian\Contracts;

use Datalogix\Guardian\Fortress;

interface CanManageTwoFactorRecoveryCodes
{
    /**
     * @param  array<int, string>  $codes
     */
    public function saveTwoFactorRecoveryCodes(Fortress $fortress, array $codes): void;
}
