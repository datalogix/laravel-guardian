<?php

namespace Datalogix\Guardian\Exceptions;

class FortressIdException extends GuardianException
{
    public static function alreadyRegistered(string $id): static
    {
        return new static("The fortress has already been registered with the ID [{$id}].");
    }

    public static function missing(): static
    {
        return new static('A fortress has been registered without an `id()`.');
    }

    public static function duplicateInRegistry(string $id): static
    {
        return new static("A fortress with the ID [{$id}] has already been registered. Fortress IDs must be unique.");
    }

    public static function tooLong(string $id, int $max): static
    {
        return new static("The fortress ID [{$id}] exceeds the maximum length of {$max} characters.");
    }

    public static function guardTooLong(string $guard, int $max): static
    {
        return new static("The auth guard [{$guard}] exceeds the maximum length of {$max} characters.");
    }
}
