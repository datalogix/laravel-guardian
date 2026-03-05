<?php

namespace Datalogix\Guardian\Contracts;

use Datalogix\Guardian\Fortress;

interface CanManageTwoFactorAuthentication
{
    public function saveTwoFactorSecret(Fortress $fortress, ?string $secret): void;
}
