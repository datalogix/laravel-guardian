<?php

namespace Datalogix\Guardian\Http\Concerns;

use Datalogix\Guardian\Guardian;
use Illuminate\Contracts\Auth\Authenticatable;

trait ChecksTwoFactorSetupAccess
{
    protected function abortIfCannotAccessTwoFactorSetup(mixed $user): void
    {
        if (! $user instanceof Authenticatable || Guardian::cannotAccess($user)) {
            abort(403);
        }
    }
}
