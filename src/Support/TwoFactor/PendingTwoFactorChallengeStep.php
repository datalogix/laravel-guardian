<?php

namespace Datalogix\Guardian\Support\TwoFactor;

use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Support\Auth\PendingAuthStep;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

class PendingTwoFactorChallengeStep implements PendingAuthStep
{
    public function hasPendingState(): bool
    {
        return Guardian::hasPendingTwoFactorChallenge();
    }

    public function resolveUser(): ?Authenticatable
    {
        return Guardian::getPendingTwoFactorChallengeUser();
    }

    public function isValid(): bool
    {
        return $this->resolveUser() instanceof Model;
    }

    public function clear(): void
    {
        Guardian::clearTwoFactorChallenge();
    }

    public function authorize(?Authenticatable $user): void
    {
        // The pending session is the authorization.
    }
}
