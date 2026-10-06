<?php

namespace Datalogix\Guardian\Exceptions;

class FrameworkConfigurationException extends GuardianException
{
    public static function dependencyMissing(string $framework, string $package): static
    {
        return new static(
            "The [{$framework}] framework integration requires the [{$package}] package, which is not installed. ".
            "Run [composer require {$package}], or pass your own route action to every feature you enable."
        );
    }
}
