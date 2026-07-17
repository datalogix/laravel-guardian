<?php

namespace Datalogix\Guardian\Support\TwoFactor;

use Datalogix\Guardian\Events\TwoFactorEnabled;
use Datalogix\Guardian\Exceptions\TwoFactorSetupException;
use Datalogix\Guardian\Guardian;
use Illuminate\Database\Eloquent\Model;

class TwoFactorSetupManager
{
    public function __construct(
        protected TwoFactorTotpVerifier $totpVerifier,
        protected TwoFactorUser $twoFactorUser,
        protected RecoveryCodes $recoveryCodes,
    ) {}

    /**
     * @return array<int, string>
     */
    public function enableFromPendingSetup(object $user, string $code): array
    {
        $pendingSecret = Guardian::getTwoFactorSetupSecret();
        $method = Guardian::getTwoFactorSetupMethod();

        if (! is_string($pendingSecret) || blank($pendingSecret)) {
            throw TwoFactorSetupException::missingPendingSecret();
        }

        if (! $this->totpVerifier->verify($pendingSecret, $code)) {
            throw TwoFactorSetupException::invalidCode();
        }

        $fortress = Guardian::getCurrentOrDefaultFortress();
        $storedSecret = $method->value.':'.$pendingSecret;

        if (! $this->twoFactorUser->canStoreTwoFactorSecret($user) || ! $this->twoFactorUser->saveTwoFactorSecret($user, $fortress, $storedSecret)) {
            Guardian::clearTwoFactorSetup();

            return [];
        }

        $recoveryCodes = [];

        if ($this->twoFactorUser->canStoreTwoFactorRecoveryCodes($user)) {
            $recoveryCodes = $this->recoveryCodes->generate();
            $this->twoFactorUser->saveTwoFactorRecoveryCodes($user, $fortress, $recoveryCodes);
        }

        Guardian::clearTwoFactorSetup();

        if (Guardian::hasPendingTwoFactorSetup()) {
            Guardian::completePendingTwoFactorSetupLogin();
        }

        if ($user instanceof Model) {
            event(new TwoFactorEnabled($fortress, $user));
        }

        return $recoveryCodes;
    }
}
