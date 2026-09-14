<?php

namespace Datalogix\Guardian\Exceptions;

use Datalogix\Guardian\Exceptions\Concerns\HasRateLimitedMessage;
use Illuminate\Validation\ValidationException;

class ResetPasswordException extends ValidationException
{
    use HasRateLimitedMessage;

    public static function forStatus(string $status): static
    {
        return static::withMessages([
            'login' => [__($status)],
        ]);
    }

    public static function rateLimited(int $seconds): static
    {
        return static::rateLimitedMessage('login', $seconds);
    }
}
