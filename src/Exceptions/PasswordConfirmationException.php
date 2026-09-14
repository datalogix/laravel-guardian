<?php

namespace Datalogix\Guardian\Exceptions;

use Datalogix\Guardian\Exceptions\Concerns\HasRateLimitedMessage;
use Illuminate\Validation\ValidationException;

class PasswordConfirmationException extends ValidationException
{
    use HasRateLimitedMessage;

    public static function invalid(): static
    {
        return static::withMessages([
            'password' => [__('auth.password')],
        ]);
    }

    public static function requiredForEnablingTwoFactor(): static
    {
        return static::withMessages([
            'password' => [__('Please confirm your password before enabling two-factor authentication.')],
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

    public static function requiredForDisconnectingOAuth(): static
    {
        return static::withMessages([
            'password' => [__('Please confirm your password before disconnecting this provider.')],
        ]);
    }

    public static function rateLimited(int $seconds): static
    {
        return static::rateLimitedMessage('password', $seconds);
    }
}
