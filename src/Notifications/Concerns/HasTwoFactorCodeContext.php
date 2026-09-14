<?php

namespace Datalogix\Guardian\Notifications\Concerns;

trait HasTwoFactorCodeContext
{
    protected function isSetupContext(): bool
    {
        return $this->context === 'setup';
    }
}
