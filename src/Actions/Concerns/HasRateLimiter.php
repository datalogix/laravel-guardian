<?php

namespace Datalogix\Guardian\Actions\Concerns;

use Closure;
use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Guardian;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Cache\RateLimiter as CacheRateLimiter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

trait HasRateLimiter
{
    /**
     * Counted before the attempt, atomically, so concurrent attempts cannot all slip under the limit.
     */
    protected function reserveAttempt(string $throttleKey, int|false|null $maxAttempts, Closure $onLockout, ?int $decaySeconds = null): mixed
    {
        if (! $this->shouldThrottle($maxAttempts)) {
            return null;
        }

        if ($this->rateLimiter()->hit($throttleKey, $decaySeconds ?? 60) <= $maxAttempts) {
            return null;
        }

        event(new Lockout(request()));

        return $onLockout($this->rateLimiter()->availableIn($throttleKey));
    }

    protected function clearRateLimiterIfThrottled(string $throttleKey, int|false|null $maxAttempts): void
    {
        if ($this->shouldThrottle($maxAttempts)) {
            $this->rateLimiter()->clear($throttleKey);
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

        if ($this->rateLimiter()->hit($throttleKey) > $maxAttempts) {
            event(new Lockout(request()));

            return $onLockout($this->rateLimiter()->availableIn($throttleKey));
        }

        $result = $callback();

        if ($clearOnSuccess) {
            $this->rateLimiter()->clear($throttleKey);
        }

        return $result ?? true;
    }

    protected function rateLimiter(): CacheRateLimiter
    {
        $store = config('guardian.cache_store');

        return $store === null ? RateLimiter::getFacadeRoot() : new CacheRateLimiter(Cache::store($store));
    }

    protected function shouldThrottle(int|false|null $maxAttempts): bool
    {
        return is_int($maxAttempts) && $maxAttempts > 0;
    }

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
