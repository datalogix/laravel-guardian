<?php

namespace Datalogix\Guardian\Tests\Fixtures;

use Datalogix\Guardian\Contracts\TwoFactorAuthenticatable;
use Datalogix\Guardian\Fortress;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * A plain (non-Eloquent) object implementing TwoFactorAuthenticatable, to
 * exercise TwoFactorUser's and HasTwoFactor's non-Model branches (e.g.
 * secretCacheKey() falls back to spl_object_id() instead of a Model's
 * primary key, and no confirmed-at timestamp can ever be tracked for it).
 */
class NonModelTwoFactorUser implements Authenticatable, TwoFactorAuthenticatable
{
    public function __construct(protected ?string $secret = 'a-secret-value') {}

    public function hasTwoFactorEnabled(Fortress $fortress): bool
    {
        return filled($this->secret);
    }

    public function getTwoFactorSecret(Fortress $fortress): ?string
    {
        return $this->secret;
    }

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthIdentifier(): int
    {
        return 1;
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    public function getAuthPassword(): string
    {
        return '';
    }

    public function getRememberToken(): ?string
    {
        return null;
    }

    public function setRememberToken($value): void
    {
        //
    }

    public function getRememberTokenName(): string
    {
        return 'remember_token';
    }
}
