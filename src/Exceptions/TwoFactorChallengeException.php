<?php

namespace Datalogix\Guardian\Exceptions;

use Illuminate\Validation\ValidationException;

class TwoFactorChallengeException extends ValidationException
{
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
        return static::withMessages([
            'code' => [__('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ])],
        ]);
    }
}
