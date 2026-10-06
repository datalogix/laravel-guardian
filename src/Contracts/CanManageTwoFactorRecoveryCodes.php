<?php

namespace Datalogix\Guardian\Contracts;

use Datalogix\Guardian\Fortress;

interface CanManageTwoFactorRecoveryCodes
{
    /**
     * @param  array<int, string>  $codes  hashed
     */
    public function saveTwoFactorRecoveryCodes(Fortress $fortress, array $codes): void;
}
