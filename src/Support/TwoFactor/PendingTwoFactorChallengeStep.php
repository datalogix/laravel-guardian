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
        // The pending challenge session itself is the access token for
        // this step; there is no additional authorization check beyond
        // existing and resolving to a valid user.
    }
}
