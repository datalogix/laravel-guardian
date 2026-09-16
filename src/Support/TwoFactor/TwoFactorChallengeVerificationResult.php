<?php

namespace Datalogix\Guardian\Support\TwoFactor;

class TwoFactorChallengeVerificationResult
{
    public function __construct(
        protected bool $valid,
        protected bool $usedRecoveryCode,
    ) {
        //
    }

    public static function invalid(): self
    {
        return new self(false, false);
    }

    public static function totpValid(): self
    {
        return new self(true, false);
    }

    public static function recoveryCodeValid(): self
    {
        return new self(true, true);
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function usedRecoveryCode(): bool
    {
        return $this->usedRecoveryCode;
    }
}
