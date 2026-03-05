<?php

namespace Datalogix\Guardian\Contracts;

use Datalogix\Guardian\Fortress;

interface TwoFactorAuthenticatable
{
    public function hasTwoFactorEnabled(Fortress $fortress): bool;

    public function getTwoFactorSecret(Fortress $fortress): ?string;
}
