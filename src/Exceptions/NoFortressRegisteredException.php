<?php

namespace Datalogix\Guardian\Exceptions;

use Exception;

class NoFortressRegisteredException extends Exception
{
    public static function make(): static
    {
        return new static('
            No fortresses have been registered.
            Please register at least one fortress using Guardian::registerFortress().
        ');
    }
}
