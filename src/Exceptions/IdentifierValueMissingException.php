<?php

namespace Datalogix\Guardian\Exceptions;

class IdentifierValueMissingException extends GuardianException
{
    public static function forUser(string $identifierColumn, string|int $userId): static
    {
        return new static(
            "Unable to resolve the [{$identifierColumn}] identifier value for user [{$userId}]. ".
            'The column is empty for this user, so a password reset link cannot be generated for it.'
        );
    }
}
