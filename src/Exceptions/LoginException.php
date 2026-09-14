<?php

namespace Datalogix\Guardian\Exceptions;

use Datalogix\Guardian\Exceptions\Concerns\HasRateLimitedMessage;
use Illuminate\Support\Facades\Lang;
use Illuminate\Validation\ValidationException;

class LoginException extends ValidationException
{
    use HasRateLimitedMessage;

    public static function invalid(): static
    {
        return static::withMessages(['login' => [__('auth.failed')]]);
    }

    public static function cannotAccess(): static
    {
        $message = Lang::has('auth.cannot-access') ? __('auth.cannot-access') : __('auth.failed');

        return static::withMessages(['login' => [$message]]);
    }

    public static function rateLimited(int $seconds): static
    {
        return static::rateLimitedMessage('login', $seconds);
    }
}
