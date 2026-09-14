<?php

namespace Datalogix\Guardian\Exceptions;

class EmailVerificationConfigurationException extends GuardianException
{
    public static function missingPromptRoute(string $fortressId): static
    {
        return new static(
            "The Fortress [{$fortressId}] requires email verification (`isRequired: true`), but its ".
            'email verification prompt route is disabled (`promptRouteAction: false`). The `verified` '.
            'middleware would redirect to a route that was never registered. Either enable the prompt route '.
            'or set `isRequired: false` in the `emailVerification()` configuration.'
        );
    }

    public static function missingVerifyRoute(string $fortressId): static
    {
        return new static(
            "The Fortress [{$fortressId}] requires email verification (`isRequired: true`), but its ".
            'email verification verify route is disabled (`verifyRouteAction: false`). The verification '.
            'email would link to a route that was never registered. Either enable the verify route '.
            'or set `isRequired: false` in the `emailVerification()` configuration.'
        );
    }
}
