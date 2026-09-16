<?php

namespace Datalogix\Guardian\Actions;

use Datalogix\Guardian\Actions\Concerns\HasRecentPasswordConfirmation;
use Datalogix\Guardian\Actions\Contracts\HasValidationRules;
use Datalogix\Guardian\Exceptions\PasswordConfirmationException;
use Datalogix\Guardian\Exceptions\TwoFactorSetupException;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Support\TwoFactor\TwoFactorSetupManager;

class EnableTwoFactor implements HasValidationRules
{
    use Concerns\HasRateLimiter;
    use HasRecentPasswordConfirmation;

    public function __construct(
        protected TwoFactorSetupManager $setupManager,
    ) {
        //
    }

    public function __invoke(object $user, array $data = []): array
    {
        if (Guardian::isAuthenticated() && ! $this->passwordWasRecentlyConfirmed()) {
            throw PasswordConfirmationException::requiredForEnablingTwoFactor();
        }

        return $this->throttleAction(
            fn () => $this->setupManager->enableFromPendingSetup(
                user: $user,
                code: (string) ($data['code'] ?? ''),
            ),
            fn (int $seconds) => throw TwoFactorSetupException::rateLimited($seconds),
            $this->userKey($user),
            Guardian::getTwoFactorSetupFeature()->getMaxAttempts(),
            includeIp: false,
            clearOnSuccess: true,
        );
    }

    public static function rules(): array
    {
        return [
            'code' => ['required', 'string', 'regex:/^\d{6}$/'],
        ];
    }
}
