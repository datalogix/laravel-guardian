<?php

namespace Datalogix\Guardian\Exceptions;

use Illuminate\Validation\ValidationException;

class PasswordConfirmationException extends ValidationException
{
    public static function invalid(): static
    {
        return static::withMessages([
            'password' => [__('auth.password')],
        ]);
    }

    public static function requiredForDisablingTwoFactor(): static
    {
        return static::withMessages([
            'password' => [__('Please confirm your password before disabling two-factor authentication.')],
        ]);
    }

    public static function requiredForRegeneratingRecoveryCodes(): static
    {
        return static::withMessages([
            'password' => [__('Please confirm your password before regenerating two-factor recovery codes.')],
        ]);
    }
}
