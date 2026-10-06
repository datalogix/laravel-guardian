<?php

namespace Datalogix\Guardian\Exceptions\Concerns;

trait HasRateLimitedMessage
{
    protected static function rateLimitedMessage(string $field, int $seconds, string $line = 'Too many attempts. Please try again in :seconds seconds.'): static
    {
        return static::withMessages([
            $field => [__($line, [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ])],
        ]);
    }
}
