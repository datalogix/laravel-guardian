<?php

namespace Datalogix\Guardian\Exceptions;

use Illuminate\Validation\ValidationException;

class TwoFactorSetupException extends ValidationException
{
    public static function invalidCode(): static
    {
        return static::withMessages(['code' => [__('auth.failed')]]);
    }

    public static function missingPendingSecret(): static
    {
        return static::withMessages(['code' => [__('auth.failed')]]);
    }
}
