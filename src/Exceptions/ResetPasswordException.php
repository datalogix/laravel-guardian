<?php

namespace Datalogix\Guardian\Exceptions;

use Illuminate\Validation\ValidationException;

class ResetPasswordException extends ValidationException
{
    public static function forStatus(string $status): static
    {
        return static::withMessages([
            'email' => [__($status)],
        ]);
    }
}
