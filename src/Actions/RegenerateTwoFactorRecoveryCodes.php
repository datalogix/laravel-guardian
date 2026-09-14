<?php

namespace Datalogix\Guardian\Actions;

use Datalogix\Guardian\Exceptions\PasswordConfirmationException;
use Datalogix\Guardian\Exceptions\TwoFactorSetupException;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Support\TwoFactor\TwoFactorLifecycleManager;

class RegenerateTwoFactorRecoveryCodes
{
    use Concerns\HasRateLimiter;
    use Concerns\HasRecentPasswordConfirmation;

    public function __construct(
        protected TwoFactorLifecycleManager $lifecycleManager,
    ) {}

    public function __invoke(object $user): array
    {
        if (! $this->passwordWasRecentlyConfirmed()) {
            throw PasswordConfirmationException::requiredForRegeneratingRecoveryCodes();
        }

        return $this->throttleAction(
            fn () => $this->lifecycleManager->regenerateRecoveryCodes($user),
            fn (int $seconds) => throw TwoFactorSetupException::rateLimited($seconds),
            $this->userKey($user),
            Guardian::getTwoFactorSetupFeature()->getMaxAttempts(),
            includeIp: false,
            clearOnSuccess: true,
        );
    }
}
