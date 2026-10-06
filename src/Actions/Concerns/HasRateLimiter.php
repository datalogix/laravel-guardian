<?php

namespace Datalogix\Guardian\Actions\Concerns;

use Closure;
use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Guardian;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\RateLimiter;

trait HasRateLimiter
{
    /**
     * Counts the attempt before it is made, and stops it past the limit. Counting
     * first, with the count the cache returns atomically, is what holds attempts
     * sent at the same time: checking first would let them all through before any
     * of them was counted. An attempt that succeeds clears the count.
     */
    protected function reserveAttempt(string $throttleKey, int|false|null $maxAttempts, Closure $onLockout, ?int $decaySeconds = null): mixed
    {
        if (! $this->shouldThrottle($maxAttempts)) {
            return null;
        }

        if (RateLimiter::hit($throttleKey, $decaySeconds ?? 60) <= $maxAttempts) {
            return null;
        }

        event(new Lockout(request()));

        return $onLockout(RateLimiter::availableIn($throttleKey));
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

        if (RateLimiter::hit($throttleKey) > $maxAttempts) {
            event(new Lockout(request()));

            return $onLockout(RateLimiter::availableIn($throttleKey));
        }

        $result = $callback();

        if ($clearOnSuccess) {
            RateLimiter::clear($throttleKey);
        }

        return $result ?? true;
    }

    protected function shouldThrottle(int|false|null $maxAttempts): bool
    {
        return is_int($maxAttempts) && $maxAttempts > 0;
    }

    /**
     * Scoped to the fortress and its guard: fortresses may have users of their own,
     * with the same IDs or logins, whose attempts must not count against each other.
     */
    protected function throttleKey(?string $key = null, bool $includeIp = true): string
    {
        $fortress = $this->throttledFortress();

        return sha1(implode('|', array_filter([
            static::class,
            $fortress->getId(),
            $fortress->getGuard(),
            $includeIp ? request()->ip() : null,
            $key,
        ])));
    }

    protected function throttledFortress(): Fortress
    {
        return $this instanceof Fortress ? $this : Guardian::getCurrentOrDefaultFortress();
    }
}
