<?php

namespace Datalogix\Guardian;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Datalogix\Guardian\GuardianManager
 */
class Guardian extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'guardian';
    }
}
