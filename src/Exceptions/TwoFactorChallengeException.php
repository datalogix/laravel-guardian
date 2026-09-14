<?php

namespace Datalogix\Guardian\Exceptions;

use Datalogix\Guardian\Exceptions\Concerns\HasRateLimitedMessage;
use Illuminate\Validation\ValidationException;

class TwoFactorChallengeException extends ValidationException
{
    use HasRateLimitedMessage;

    public static function invalid(): static
    {
        return static::withMessages(['code' => [__('auth.failed')]]);
    }

    public static function notPending(): static
    {
        return static::withMessages(['code' => [__('auth.failed')]]);
    }

    public static function rateLimited(int $seconds): static
    {
        return static::rateLimitedMessage('code', $seconds);
    }
}
