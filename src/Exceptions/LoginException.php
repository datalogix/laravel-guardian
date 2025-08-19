<?php

namespace Datalogix\Guardian\Exceptions;

use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Validation\ValidationException;

class LoginException extends ValidationException
{
    public static function invalid(): static
    {
        throw ValidationException::withMessages(['login' => [__('auth.failed')]]);
    }

    public static function cannotAccess(Guard|StatefulGuard $auth): static
    {
        $message = __('auth.cannot-access') === 'auth.cannot-access' ? __('auth.failed') : __('auth.cannot-access');

        throw ValidationException::withMessages(['login' => [$message]]);
    }

    public static function rateLimited(int $seconds): static
    {
        throw ValidationException::withMessages([
            'login' => [__('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ])],
        ]);
    }
}
