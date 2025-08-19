<?php

namespace Datalogix\Guardian\Concerns;

use Datalogix\Guardian\Contracts\FortressUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Database\Eloquent\Model;

trait HasAuth
{
    protected string $guard = 'web';

    public function auth(): Guard|StatefulGuard
    {
        return auth()->guard($this->getGuard());
    }

    public function user(): ?Authenticatable
    {
        return $this->auth()->user();
    }

    public function guard(string $guard): static
    {
        $this->guard = $guard;

        return $this;
    }

    public function getGuard(): string
    {
        return $this->guard;
    }

    public function canAccess(Model $user): bool
    {
        if (! $user instanceof FortressUser) {
            return true;
        }

        return $user->canAccessFortress($this);
    }

    public function cannotAccess(Model $user): bool
    {
        return ! $this->canAccess($user);
    }
}
