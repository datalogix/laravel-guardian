<?php

namespace Datalogix\Guardian\Support\TwoFactor;

use Datalogix\Guardian\Enums\TwoFactorMethod;
use Datalogix\Guardian\Events\TwoFactorEnabled;
use Datalogix\Guardian\Exceptions\TwoFactorSetupException;
use Datalogix\Guardian\Guardian;
use Illuminate\Database\Eloquent\Model;

class TwoFactorSetupManager
{
    public function __construct(
        protected TwoFactorUser $twoFactorUser,
        protected RecoveryCodes $recoveryCodes,
        protected Totp $totp,
        protected DeliveredCodes $deliveredCodes,
    ) {}

    public function enableFromPendingSetup(object $user, string $code): array
    {
        $session = Guardian::getTwoFactorSetupSession();
        $pendingSecret = $session['secret'] ?? null;
        $method = TwoFactorMethod::tryFrom((string) ($session['method'] ?? '')) ?? Guardian::getTwoFactorMethod();

        if (! is_string($pendingSecret) || blank($pendingSecret)) {
            throw TwoFactorSetupException::missingPendingSecret();
        }

        $fortress = Guardian::getCurrentOrDefaultFortress();

        $valid = $method->requiresDelivery()
            ? $this->deliveredCodes->verify($fortress->getTwoFactorSetupSessionKey(), $code, $fortress->getTwoFactorSetupTtl())
            : $this->totp->verify($pendingSecret, $code, 1) !== false;

        if (! $valid) {
            throw TwoFactorSetupException::invalidCode();
        }
        $storedSecret = $method->value.':'.$pendingSecret;

        if (! $this->twoFactorUser->canStoreTwoFactorSecret($user) || ! $this->twoFactorUser->saveTwoFactorSecret($user, $fortress, $storedSecret)) {
            Guardian::clearTwoFactorSetup();

            throw TwoFactorSetupException::unableToStoreSecret();
        }

        $recoveryCodes = [];

        if ($this->twoFactorUser->canStoreTwoFactorRecoveryCodes($user)) {
            $recoveryCodes = $this->recoveryCodes->generate();
            $this->twoFactorUser->saveTwoFactorRecoveryCodes($user, $fortress, $recoveryCodes);
        }

        Guardian::clearTwoFactorSetup();

        app(TwoFactorSecurityChange::class)->apply($user);

        if (Guardian::hasPendingTwoFactorSetup()) {
            Guardian::completePendingTwoFactorSetupLogin();
        }

        if ($user instanceof Model) {
            event(new TwoFactorEnabled($fortress, $user));
        }

        return $recoveryCodes;
    }
}
