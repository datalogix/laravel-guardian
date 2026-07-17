<?php

namespace Datalogix\Guardian\Concerns;

use Datalogix\Guardian\Contracts\FortressUser;
use Datalogix\Guardian\Exceptions\UnsupportedAuthGuardException;
use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Database\Eloquent\Model;

trait HasAuth
{
    protected string $guard = 'web';

    public function auth(): Guard|StatefulGuard
    {
        return auth()->guard($this->getGuard());
    }

    public function isAuthenticated(): bool
    {
        return $this->auth()->check();
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

    public function authWithProvider(): SessionGuard
    {
        $auth = $this->auth();

        if (! $auth instanceof SessionGuard) {
            throw UnsupportedAuthGuardException::forGuard($this->getGuard(), $auth::class);
        }

        return $auth;
    }

    public function authProvider(): UserProvider
    {
        return $this->authWithProvider()->getProvider();
    }

    public function authModelClass(): string
    {
        $provider = $this->authProvider();

        if (! method_exists($provider, 'getModel')) {
            throw UnsupportedAuthGuardException::forProvider($this->getGuard(), $provider::class);
        }

        return $provider->getModel();
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
