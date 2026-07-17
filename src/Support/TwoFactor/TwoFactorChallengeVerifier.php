<?php

namespace Datalogix\Guardian\Support\TwoFactor;

use Datalogix\Guardian\Fortress;
use Illuminate\Database\Eloquent\Model;

class TwoFactorChallengeVerifier
{
    public function __construct(
        protected TwoFactorUser $twoFactorUser,
        protected TwoFactorTotpVerifier $totpVerifier,
    ) {}

    public function verify(Model $user, Fortress $fortress, string $code): TwoFactorChallengeVerificationResult
    {
        $secret = $this->twoFactorUser->getTwoFactorSecret($user, $fortress);

        if ($this->totpVerifier->verify($secret, $code)) {
            return TwoFactorChallengeVerificationResult::totpValid();
        }

        $consumed = $this->twoFactorUser->consumeTwoFactorRecoveryCode($user, $fortress, $code);

        if ($consumed) {
            return TwoFactorChallengeVerificationResult::recoveryCodeValid();
        }

        return TwoFactorChallengeVerificationResult::invalid();
    }
}
