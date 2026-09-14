<?php

namespace Datalogix\Guardian\Support\TwoFactor;

use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Http\Concerns\ChecksTwoFactorSetupAccess;
use Datalogix\Guardian\Support\Auth\PendingAuthStep;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

class PendingTwoFactorSetupStep implements PendingAuthStep
{
    use ChecksTwoFactorSetupAccess;

    public function hasPendingState(): bool
    {
        return Guardian::isAuthenticated() || Guardian::hasPendingTwoFactorSetup();
    }

    public function resolveUser(): ?Authenticatable
    {
        if (Guardian::isAuthenticated()) {
            return Guardian::user();
        }

        $user = Guardian::getPendingTwoFactorSetupUser();

        return $user instanceof Authenticatable ? $user : null;
    }

    public function isValid(): bool
    {
        if (Guardian::isAuthenticated()) {
            return true;
        }

        return Guardian::getPendingTwoFactorSetupUser() instanceof Model;
    }

    public function clear(): void
    {
        Guardian::clearPendingTwoFactorSetup();
    }

    public function authorize(?Authenticatable $user): void
    {
        $this->abortIfCannotAccessTwoFactorSetup($user);
    }
}
