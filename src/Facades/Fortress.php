<?php

namespace Datalogix\Fortress\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Datalogix\Fortress\FortressManager
 */
class Fortress extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'fortress';
    }
}
