<?php

namespace Datalogix\Guardian\Actions;

use Datalogix\Guardian\Exceptions\PasswordConfirmationException;
use Datalogix\Guardian\Support\TwoFactor\TwoFactorLifecycleManager;

class DisableTwoFactor
{
    use Concerns\HasRecentPasswordConfirmation;

    public function __construct(
        protected TwoFactorLifecycleManager $lifecycleManager,
    ) {}

    public function __invoke(object $user): void
    {
        if (! $this->passwordWasRecentlyConfirmed()) {
            throw PasswordConfirmationException::requiredForDisablingTwoFactor();
        }

        $this->lifecycleManager->disable($user);
    }
}
