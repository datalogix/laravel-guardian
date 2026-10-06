<?php

namespace Datalogix\Guardian\Tests\Fixtures;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\UserProvider;

/**
 * A guard whose provider has no getModel().
 */
class BareGuardWithProvider implements Guard
{
    public function getProvider(): UserProvider
    {
        return new BareUserProvider;
    }

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

    public function setUser(Authenticatable $user): void {}
}
