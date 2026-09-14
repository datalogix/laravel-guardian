<?php

namespace Datalogix\Guardian\Http\Middleware;

use Datalogix\Guardian\Support\TwoFactor\PendingTwoFactorSetupStep;

class EnsureTwoFactorSetupAccess extends EnsurePendingAuthStepAccess
{
    public function __construct(PendingTwoFactorSetupStep $step)
    {
        parent::__construct($step);
    }
}
