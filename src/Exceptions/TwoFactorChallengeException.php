<?php

namespace Datalogix\Guardian\Exceptions;

use Illuminate\Validation\ValidationException;

class TwoFactorChallengeException extends ValidationException
{
    public static function invalid(): static
    {
        throw ValidationException::withMessages(['code' => [__('auth.failed')]]);
    }

    public static function notPending(): static
    {
        throw ValidationException::withMessages(['code' => [__('auth.failed')]]);
    }

    public static function rateLimited(int $seconds): static
    {
        throw ValidationException::withMessages([
            'code' => [__('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ])],
        ]);
    }
}
