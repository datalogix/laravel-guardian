<?php

namespace Datalogix\Guardian;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Datalogix\Guardian\GuardianManager
 *
 * @mixin \Datalogix\Guardian\Fortress
 */
class Guardian extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'guardian';
    }
}
