<?php

namespace Datalogix\Guardian\Exceptions;

class FortressRouteCollisionException extends GuardianException
{
    public static function make(string $route, string $owner, string $fortress): static
    {
        return new static(
            "The fortresses [{$owner}] and [{$fortress}] both register [{$route}]. "
            .'The last one would silently take the page of the other: give one of them its own path() or domain().'
        );
    }
}
