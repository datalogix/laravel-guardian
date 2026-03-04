<?php

namespace Datalogix\Guardian\Exceptions;

class NoFortressRegisteredException extends GuardianException
{
    public static function make(): static
    {
        return new static(
            'No fortresses have been registered. '
            .'Please register at least one fortress using Guardian::registerFortress().'
        );
    }
}
