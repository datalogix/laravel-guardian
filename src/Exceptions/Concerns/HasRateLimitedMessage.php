<?php

namespace Datalogix\Guardian\Exceptions\Concerns;

trait HasRateLimitedMessage
{
    /**
     * Laravel's auth.throttle speaks of login attempts, so it is kept for the steps
     * of signing in; everything else is told about attempts in general.
     */
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
