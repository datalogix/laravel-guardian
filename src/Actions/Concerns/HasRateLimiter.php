<?php

namespace Datalogix\Guardian\Actions\Concerns;

use Closure;
use Datalogix\Guardian\Guardian;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

trait HasRateLimiter
{
    protected function ensureIsNotRateLimited(string $throttleKey, int|false|null $maxAttempts, Closure $onLockout): void
    {
        if (! $this->shouldThrottle($maxAttempts)) {
            return;
        }

        if (! RateLimiter::tooManyAttempts($throttleKey, $maxAttempts)) {
            return;
        }

        event(new Lockout(request()));

        $onLockout(RateLimiter::availableIn($throttleKey));
    }

    protected function hitRateLimiterIfThrottled(string $throttleKey, int|false|null $maxAttempts, ?int $decaySeconds = null): void
    {
        if (! $this->shouldThrottle($maxAttempts)) {
            return;
        }

        if ($decaySeconds === null) {
            RateLimiter::hit($throttleKey);
        } else {
            RateLimiter::hit($throttleKey, $decaySeconds);
        }
    }

    protected function clearRateLimiterIfThrottled(string $throttleKey, int|false|null $maxAttempts): void
    {
        if ($this->shouldThrottle($maxAttempts)) {
            RateLimiter::clear($throttleKey);
        }
    }

    protected function userKey(object $user): ?string
    {
        if (! method_exists($user, 'getAuthIdentifier')) {
            return null;
        }

        return implode('|', [
            Guardian::getGuard(),
            Guardian::getId(),
            $user->getAuthIdentifier(),
        ]);
    }

    protected function throttleAction(
        Closure $callback,
        Closure $onLockout,
        ?string $key = null,
        int|false|null $maxAttempts = null,
        bool $includeIp = true,
        bool $clearOnSuccess = false,
    ) {
        if (! $this->shouldThrottle($maxAttempts)) {
            return $callback();
        }

        $throttleKey = $this->throttleKey($key, $includeIp);

        if (RateLimiter::tooManyAttempts($throttleKey, $maxAttempts)) {
            event(new Lockout(request()));

            return $onLockout(RateLimiter::availableIn($throttleKey));
        }

        try {
            $result = $callback();
        } catch (Throwable $exception) {
            RateLimiter::hit($throttleKey);

            throw $exception;
        }

        if ($clearOnSuccess) {
            RateLimiter::clear($throttleKey);
        } else {
            RateLimiter::hit($throttleKey);
        }

        return $result ?? true;
    }

    protected function shouldThrottle(int|false|null $maxAttempts): bool
    {
        return is_int($maxAttempts) && $maxAttempts > 0;
    }

    protected function throttleKey(?string $key = null, bool $includeIp = true): string
    {
        return sha1(implode('|', array_filter([
            static::class,
            $includeIp ? request()->ip() : null,
            $key,
        ])));
    }
}
