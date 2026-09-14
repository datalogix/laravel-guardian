<?php

namespace Datalogix\Guardian\Exceptions;

class IdentifierColumnConfigurationException extends GuardianException
{
    public static function missingColumn(string $fortressId, string $modelClass, string $column): static
    {
        return new static(
            "The Fortress [{$fortressId}] uses [{$column}] as its identifier key, but the model ".
            "[{$modelClass}] has no [{$column}] column/attribute. Add the column, or configure a ".
            'different identifierKey() for this Fortress.'
        );
    }

    public static function missingEmailColumn(string $fortressId, string $modelClass): static
    {
        return new static(
            "The Fortress [{$fortressId}] has SignUp, OAuth, password reset, or required email ".
            "verification enabled, which require an [email] column, but the model [{$modelClass}] ".
            'has no [email] column/attribute. Add the column, or disable those features for this Fortress.'
        );
    }
}
