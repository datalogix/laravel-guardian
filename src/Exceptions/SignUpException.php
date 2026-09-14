<?php

namespace Datalogix\Guardian\Exceptions;

use Datalogix\Guardian\Exceptions\Concerns\HasRateLimitedMessage;
use Illuminate\Validation\ValidationException;

class SignUpException extends ValidationException
{
    use HasRateLimitedMessage;

    public static function rateLimited(int $seconds): static
    {
        return static::rateLimitedMessage('login', $seconds);
    }

    public static function cannotAccess(): static
    {
        return static::withMessages(['login' => [__('auth.failed')]]);
    }

    public static function emailAlreadyExists(): static
    {
        return static::withMessages([
            'login' => [__('validation.unique', ['attribute' => 'email'])],
        ]);
    }

    public static function unableToRegister(): static
    {
        return static::withMessages(['login' => [__('auth.failed')]]);
    }
}
