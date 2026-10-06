<?php

namespace Datalogix\Guardian\Support\TwoFactor;

use Datalogix\Guardian\Enums\TwoFactorMethod;
use Datalogix\Guardian\Fortress;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class TwoFactorChallengeVerifier
{
    public function __construct(
        protected TwoFactorUser $twoFactorUser,
        protected Totp $totp,
        protected DeliveredCodes $deliveredCodes,
    ) {
        //
    }

    public function verify(Model $user, Fortress $fortress, string $code): TwoFactorChallengeVerificationResult
    {
        $secret = $this->twoFactorUser->getTwoFactorSecret($user, $fortress);
        $method = $this->twoFactorUser->getTwoFactorMethod($user, $fortress);

        // A secret means two-factor authentication is still enabled.
        if (is_string($secret) && $this->verifyCode($user, $fortress, $method, $secret, $code)) {
            return TwoFactorChallengeVerificationResult::totpValid();
        }

        $consumed = $this->twoFactorUser->consumeTwoFactorRecoveryCode($user, $fortress, $code);

        if ($consumed) {
            return TwoFactorChallengeVerificationResult::recoveryCodeValid();
        }

        return TwoFactorChallengeVerificationResult::invalid();
    }

    protected function verifyCode(Model $user, Fortress $fortress, TwoFactorMethod $method, string $secret, string $code): bool
    {
        if ($method->requiresDelivery()) {
            return $this->deliveredCodes->verify($fortress->getTwoFactorChallengeSessionKey(), $code, $fortress->getTwoFactorChallengeTtl());
        }

        // A code of the authenticator app is accepted once, one step either side of now.
        $cacheKey = $this->replayCacheKey($user, $fortress);
        $oldTimestamp = Cache::get($cacheKey);

        $result = $this->totp->verify($secret, $code, 1, is_int($oldTimestamp) ? $oldTimestamp : null);

        // Claimed atomically: requests sending the same code at the same time would
        // all read the same last used step, so only the first to claim it gets in.
        if ($result === false || ! Cache::add($cacheKey.':'.$result, true, now()->addSeconds(90))) {
            return false;
        }

        Cache::put($cacheKey, (int) $result, now()->addSeconds(90));

        return true;
    }

    protected function replayCacheKey(Model $user, Fortress $fortress): string
    {
        return implode(':', ['guardian:two-factor:totp-last-used', $fortress->getId(), $user::class, $user->getKey()]);
    }
}
