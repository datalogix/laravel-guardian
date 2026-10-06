<?php

namespace Datalogix\Guardian\Support\OAuth;

use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Support\Auth\PendingAuthStep;
use Illuminate\Contracts\Auth\Authenticatable;

class PendingOAuthRegistrationStep implements PendingAuthStep
{
    public function hasPendingState(): bool
    {
        return Guardian::hasPendingOAuthRegistration();
    }

    public function resolveUser(): ?Authenticatable
    {
        return null;
    }

    public function isValid(): bool
    {
        return true;
    }

    public function clear(): void
    {
        Guardian::clearPendingOAuthRegistration();
    }

    public function authorize(?Authenticatable $user): void
    {
        // The pending session is the authorization: there is no user yet.
    }
}
