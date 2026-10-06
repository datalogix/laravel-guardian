<?php

namespace Datalogix\Guardian\Tests\Fixtures;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;

/**
 * A minimal guard that implements the base Guard contract only — no
 * getProvider(), unlike SessionGuard — to exercise HasAuth's
 * UnsupportedAuthGuardException branches for non-StatefulGuard guards.
 */
class BareGuard implements Guard
{
    public function check(): bool
    {
        return false;
    }

    public function guest(): bool
    {
        return true;
    }

    public function user(): ?Authenticatable
    {
        return null;
    }

    public function id(): int|string|null
    {
        return null;
    }

    public function validate(array $credentials = []): bool
    {
        return false;
    }

    public function hasUser(): bool
    {
        return false;
    }

    public function setUser(Authenticatable $user): void
    {
        //
    }
}
