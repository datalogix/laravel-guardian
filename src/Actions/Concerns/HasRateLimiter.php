<?php

namespace Datalogix\Guardian\Actions\Concerns;

use Closure;
use Illuminate\Support\Facades\RateLimiter;

trait HasRateLimiter
{
    protected function throttleAction(
        Closure $callback,
        ?string $key = null,
        int|false|null $maxAttempts = null
    ) {
        if (! $this->shouldThrottle($maxAttempts)) {
            return $callback();
        }

        return RateLimiter::attempt(
            $this->throttleKey($key),
            $maxAttempts,
            $callback,
        );
    }

    protected function shouldThrottle(int|false|null $maxAttempts): bool
    {
        return is_int($maxAttempts) && $maxAttempts > 0;
    }

    protected function throttleKey(?string $key = null)
    {
        return sha1(implode('|', array_filter([
            static::class,
            request()->ip(),
            $key,
        ])));
    }
}
