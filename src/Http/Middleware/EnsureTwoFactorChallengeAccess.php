<?php

namespace Datalogix\Guardian\Http\Middleware;

use Datalogix\Guardian\Support\TwoFactor\PendingTwoFactorChallengeStep;

class EnsureTwoFactorChallengeAccess extends EnsurePendingAuthStepAccess
{
    public function __construct(PendingTwoFactorChallengeStep $step)
    {
        parent::__construct($step);
    }
}
