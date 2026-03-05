<?php

namespace Datalogix\Guardian\Exceptions;

use Illuminate\Validation\ValidationException;

class TwoFactorSetupException extends ValidationException
{
    public static function invalidCode(): static
    {
        throw ValidationException::withMessages(['code' => [__('auth.failed')]]);
    }

    public static function missingPendingSecret(): static
    {
        throw ValidationException::withMessages(['code' => [__('auth.failed')]]);
    }
}
