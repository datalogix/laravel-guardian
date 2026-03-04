<?php

namespace Datalogix\Guardian\Exceptions;

class NoDefaultFortressSetException extends GuardianException
{
    public static function make(): static
    {
        return new static(
            'No default Fortress is set. '
            .'You may do this with the `default()` method inside a Guardian provider\'s `fortress()` configuration.'
        );
    }
}
