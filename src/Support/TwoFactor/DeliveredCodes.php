<?php

namespace Datalogix\Guardian\Support\TwoFactor;

use Datalogix\Guardian\Support\SessionState;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

/**
 * Codes sent by e-mail or SMS: only a keyed hash is kept, each is accepted once,
 * and a few wrong guesses discard it.
 */
class DeliveredCodes
{
    public const MAX_WRONG_ATTEMPTS = 5;

    protected const KEY = 'delivered_code';

    public function __construct(
        protected SessionState $state,
    ) {}

    public function issue(string $sessionKey, int|false|null $ttl): ?string
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $session = $this->state->update($sessionKey, fn (array $session) => [
            ...$session,
            self::KEY => ['hash' => $this->hash($code), 'issued_at' => now()->timestamp],
        ], $ttl);

        return $session === null ? null : $code;
    }

    public function verify(string $sessionKey, string $code, int|false|null $ttl): bool
    {
        $delivered = $this->state->getValid($sessionKey, $ttl)[self::KEY] ?? null;

        if (! is_array($delivered) || $this->hasExpired($delivered, $ttl)) {
            $this->discard($sessionKey, $ttl);

            return false;
        }

        // Counted in the cache, atomically: concurrent guesses would read the same count from the session.
        $attemptsKey = 'guardian:two-factor:delivered-code:'.$delivered['hash'];
        $this->cache()->add($attemptsKey, 0, $this->attemptsTtl($ttl));
        $attempt = (int) $this->cache()->increment($attemptsKey);

        $valid = $attempt <= self::MAX_WRONG_ATTEMPTS
            && hash_equals($delivered['hash'], $this->hash((string) preg_replace('/\s+/', '', $code)))
            // Claimed atomically, so concurrent requests sign in once.
            && $this->cache()->add('guardian:two-factor:delivered-code-used:'.$delivered['hash'], true, $this->attemptsTtl($ttl));

        // The count stays until it expires, for requests still holding the old session.
        if ($valid || $attempt >= self::MAX_WRONG_ATTEMPTS) {
            $this->discard($sessionKey, $ttl);
        }

        return $valid;
    }

    protected function discard(string $sessionKey, int|false|null $ttl): void
    {
        $this->state->update($sessionKey, function (array $session) {
            unset($session[self::KEY]);

            return $session;
        }, $ttl);
    }

    protected function attemptsTtl(int|false|null $ttl): int
    {
        return is_int($ttl) && $ttl > 0 ? $ttl : 86400;
    }

    protected function hasExpired(array $delivered, int|false|null $ttl): bool
    {
        return is_int($ttl) && $ttl > 0 && ($delivered['issued_at'] + $ttl) < now()->timestamp;
    }

    /**
     * Keyed, so the session store alone does not reveal the code.
     */
    protected function hash(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }

    protected function cache(): Repository
    {
        return Cache::store(config('guardian.cache_store'));
    }
}
