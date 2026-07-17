<?php

namespace Datalogix\Guardian\Actions;

use Datalogix\Guardian\Exceptions\PasswordConfirmationException;
use Datalogix\Guardian\Support\TwoFactor\TwoFactorLifecycleManager;

class RegenerateTwoFactorRecoveryCodes
{
    use Concerns\HasRecentPasswordConfirmation;

    public function __construct(
        protected TwoFactorLifecycleManager $lifecycleManager,
    ) {}

    /**
     * @return array<int, string>
     */
    public function __invoke(object $user): array
    {
        if (! $this->passwordWasRecentlyConfirmed()) {
            throw PasswordConfirmationException::requiredForRegeneratingRecoveryCodes();
        }

        return $this->lifecycleManager->regenerateRecoveryCodes($user);
    }
}
