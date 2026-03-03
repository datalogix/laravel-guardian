<?php

namespace Datalogix\Guardian\Exceptions;

use Exception;

class MultipleDefaultFortressesException extends Exception
{
    public static function make(): static
    {
        return new static('
            Multiple default fortresses have been set.
            Only one fortress can be the default.
        ');
    }
}
