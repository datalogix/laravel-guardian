<?php

namespace Datalogix\Guardian;

use Illuminate\Support\Facades\Facade;

/**
 * @see GuardianManager
 *
 * @mixin Fortress
 */
class Guardian extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'guardian';
    }
}
