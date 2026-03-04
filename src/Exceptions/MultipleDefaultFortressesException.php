<?php

namespace Datalogix\Guardian\Exceptions;

class MultipleDefaultFortressesException extends GuardianException
{
    public static function make(): static
    {
        return new static(
            'Multiple default fortresses have been set. '
            .'Only one fortress can be the default.'
        );
    }
}
