<?php

namespace Datalogix\Guardian\Support;

use Illuminate\Support\Facades\Session;

class SessionState
{
    public function put(string $key, array $state): void
    {
        Session::put($key, $state);
    }

    public function get(string $key): ?array
    {
        $value = Session::get($key);

        return is_array($value) ? $value : null;
    }

    public function getValid(string $key, int|false|null $ttl): ?array
    {
        $state = $this->get($key);

        if (! $state) {
            return null;
        }

        if ($this->isExpired($state, $ttl)) {
            $this->forget($key);

            return null;
        }

        return $state;
    }

    public function update(string $key, callable $mutator, int|false|null $ttl = null): ?array
    {
        $state = $this->getValid($key, $ttl);

        if (! $state) {
            return null;
        }

        $updated = $mutator($state);

        if (! is_array($updated)) {
            return null;
        }

        $this->put($key, $updated);

        return $updated;
    }

    public function forget(string $key): void
    {
        Session::forget($key);
    }

    protected function isExpired(array $state, int|false|null $ttl): bool
    {
        if (! is_int($ttl) || $ttl <= 0) {
            return false;
        }

        $startedAt = $state['started_at'] ?? null;

        if (! is_int($startedAt)) {
            return true;
        }

        return ($startedAt + $ttl) < now()->timestamp;
    }
}
