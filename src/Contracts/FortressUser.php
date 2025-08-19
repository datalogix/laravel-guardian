<?php

namespace Datalogix\Guardian\Contracts;

use Datalogix\Guardian\Fortress;

interface FortressUser
{
    public function canAccessFortress(Fortress $fortress): bool;
}
