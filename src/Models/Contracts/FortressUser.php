<?php

namespace Datalogix\Fortress\Models\Contracts;

use Datalogix\Fortress\Fortress;

interface FortressUser
{
    public function canAccessFortress(Fortress $fortress): bool;
}
