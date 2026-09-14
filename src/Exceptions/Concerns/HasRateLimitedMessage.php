<?php

namespace Datalogix\Guardian\Exceptions\Concerns;

trait HasRateLimitedMessage
{
    protected static function rateLimitedMessage(string $field, int $seconds): static
    {
        return static::withMessages([
            $field => [__('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ])],
        ]);
    }
}
