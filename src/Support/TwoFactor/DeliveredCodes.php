<?php

namespace Datalogix\Guardian\Support\TwoFactor;

use Datalogix\Guardian\Support\SessionState;
use Illuminate\Support\Facades\Cache;

/**
 * The codes sent by e-mail or SMS. Every delivery is a random code of its own, of
 * which the session of the step keeps a keyed hash: it is accepted once, for the
 * TTL of the step, and a few wrong guesses throw it away, so it cannot be worked
 * out one attempt at a time. Only the code last sent is valid.
 */
class DeliveredCodes
{
    public const MAX_WRONG_ATTEMPTS = 5;

    protected const KEY = 'delivered_code';

    public function __construct(
        protected SessionState $state,
    ) {
        //
    }

    /**
     * A new code for the step stored under the session key, or null when the step
     * is no longer pending.
     */
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

        // Counted before the code is compared, in the cache, which counts atomically:
        // guesses sent at the same time would all read the same count from the session.
        $attemptsKey = 'guardian:two-factor:delivered-code:'.$delivered['hash'];
        Cache::add($attemptsKey, 0, $this->attemptsTtl($ttl));
        $attempt = (int) Cache::increment($attemptsKey);

        $valid = $attempt <= self::MAX_WRONG_ATTEMPTS
            && hash_equals($delivered['hash'], $this->hash((string) preg_replace('/\s+/', '', $code)))
            // Claimed atomically, so the code signs in one request even when it is sent
            // by several at the same time.
            && Cache::add('guardian:two-factor:delivered-code-used:'.$delivered['hash'], true, $this->attemptsTtl($ttl));

        // Used once, or thrown away with the last guess it had. The count stays until
        // it expires, for the requests still holding the session from before.
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
     * Keyed, so that the session store alone does not give the code away to anyone
     * trying the million possible ones.
     */
    protected function hash(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }
}
