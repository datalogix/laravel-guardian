<?php

namespace Datalogix\Fortress\Exceptions;

use Exception;

class NoDefaultFortressSetException extends Exception
{
    public static function make(): static
    {
        return new static('
            No default Fortress is set.
            You may do this with the `default()` method inside a Fortress provider\'s `fortress()` configuration.
        ');
    }
}
