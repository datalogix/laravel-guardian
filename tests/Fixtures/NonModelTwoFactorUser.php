<?php

namespace Datalogix\Guardian\Tests\Fixtures;

use Datalogix\Guardian\Contracts\TwoFactorAuthenticatable;
use Datalogix\Guardian\Fortress;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * A non-Eloquent user implementing TwoFactorAuthenticatable.
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

    public function setRememberToken($value): void {}

    public function getRememberTokenName(): string
    {
        return 'remember_token';
    }
}
