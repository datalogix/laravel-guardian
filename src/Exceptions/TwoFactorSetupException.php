<?php

namespace Datalogix\Guardian\Exceptions;

use Datalogix\Guardian\Exceptions\Concerns\HasRateLimitedMessage;
use Illuminate\Validation\ValidationException;

class TwoFactorSetupException extends ValidationException
{
    use HasRateLimitedMessage;

    public static function invalidCode(): static
    {
        return static::failed();
    }

    public static function missingPendingSecret(): static
    {
        return static::failed();
    }

    public static function requiredByFortress(): static
    {
        return static::withMessages(['code' => [__('Two-factor authentication is required for your account, so it cannot be disabled.')]]);
    }

    public static function unableToStoreSecret(): static
    {
        return static::withMessages(['code' => [__('We could not save your two-factor settings. Please try again later.')]]);
    }

    protected static function failed(): static
    {
        return static::withMessages(['code' => [__('auth.failed')]]);
    }

    public static function invalidAccountLabel(): static
    {
        return static::withMessages([
            'code' => [__('Could not determine a valid account label for two-factor setup.')],
        ]);
    }

    public static function rateLimited(int $seconds): static
    {
        return static::rateLimitedMessage('code', $seconds);
    }
}
