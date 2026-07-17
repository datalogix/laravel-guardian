<?php

namespace Datalogix\Guardian\Exceptions;

use RuntimeException;

class UnsupportedAuthGuardException extends RuntimeException
{
    public static function forGuard(string $guardName, string $guardClass): self
    {
        return new self("Guardian guard [{$guardName}] must be an instance of Illuminate\\Auth\\SessionGuard to access a user provider. Current guard: [{$guardClass}].");
    }

    public static function forProvider(string $guardName, string $providerClass): self
    {
        return new self("Guardian guard [{$guardName}] resolved provider [{$providerClass}] that does not expose getModel().");
    }
}
