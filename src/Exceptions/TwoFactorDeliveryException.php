<?php

namespace Datalogix\Guardian\Exceptions;

use Datalogix\Guardian\Enums\TwoFactorMethod;
use Illuminate\Validation\ValidationException;

class TwoFactorDeliveryException extends ValidationException
{
    public static function unavailableMethod(TwoFactorMethod $method): static
    {
        if ($method === TwoFactorMethod::Sms) {
            return static::withMessages([
                'code' => [__('SMS two-factor delivery is not configured. Install a Laravel notifications SMS channel (for example Vonage) or define sendSmsCodeUsing in twoFactor().')],
            ]);
        }

        return static::withMessages([
            'code' => [__('Two-factor delivery is not configured for method: :method', ['method' => $method->value])],
        ]);
    }

    public static function missingRecipient(TwoFactorMethod $method): static
    {
        return static::withMessages([
            'code' => [__('Could not determine a recipient for two-factor method: :method', ['method' => $method->value])],
        ]);
    }
}
