<?php

namespace Datalogix\Guardian\Concerns;

use Datalogix\Guardian\Contracts\FortressUser;
use Datalogix\Guardian\Exceptions\FortressIdException;
use Datalogix\Guardian\Exceptions\UnsupportedAuthGuardException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Auth\UserProvider;

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
        if (strlen($guard) > HasId::MAX_ID_LENGTH) {
            throw FortressIdException::guardTooLong($guard, HasId::MAX_ID_LENGTH);
        }

        $this->guard = $guard;

        return $this;
    }

    public function getGuard(): string
    {
        return $this->guard;
    }

    public function authWithProvider(): Guard
    {
        $auth = $this->auth();

        if (! method_exists($auth, 'getProvider')) {
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

    public function canAccess(Authenticatable $user): bool
    {
        if (! $user instanceof FortressUser) {
            return true;
        }

        return $user->canAccessFortress($this);
    }

    public function cannotAccess(Authenticatable $user): bool
    {
        return ! $this->canAccess($user);
    }
}
