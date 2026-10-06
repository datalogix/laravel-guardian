<?php

namespace Datalogix\Guardian\Exceptions;

class EmailVerificationThrottledException extends GuardianException
{
    public function __construct(public readonly int $seconds)
    {
        parent::__construct("The verification e-mail can be sent again in {$seconds} seconds.");
    }
}
