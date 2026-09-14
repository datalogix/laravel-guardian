<?php

namespace Datalogix\Guardian\Http\Middleware;

use Datalogix\Guardian\Support\OAuth\PendingOAuthRegistrationStep;

class EnsureOAuthCompleteRegistrationAccess extends EnsurePendingAuthStepAccess
{
    public function __construct(PendingOAuthRegistrationStep $step)
    {
        parent::__construct($step);
    }
}
