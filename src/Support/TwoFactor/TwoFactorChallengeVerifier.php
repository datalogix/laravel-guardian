<?php

namespace Datalogix\Guardian\Support\TwoFactor;

use Datalogix\Guardian\Fortress;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class TwoFactorChallengeVerifier
{
    public function __construct(
        protected TwoFactorUser $twoFactorUser,
        protected Totp $totp,
    ) {}

    public function verify(Model $user, Fortress $fortress, string $code): TwoFactorChallengeVerificationResult
    {
        $secret = $this->twoFactorUser->getTwoFactorSecret($user, $fortress);
        $method = $this->twoFactorUser->getTwoFactorMethod($user, $fortress);
        $window = $this->totp->windowFor($method, $fortress->getTwoFactorChallengeTtl());

        if (is_string($secret)) {
            $cacheKey = $this->replayCacheKey($user, $fortress);
            $oldTimestamp = Cache::get($cacheKey);

            $result = $this->totp->verify($secret, $code, $window, is_int($oldTimestamp) ? $oldTimestamp : null);

            if ($result !== false) {
                Cache::put($cacheKey, (int) $result, now()->addSeconds(($window * 2 + 1) * 30));

                return TwoFactorChallengeVerificationResult::totpValid();
            }
        }

        $consumed = $this->twoFactorUser->consumeTwoFactorRecoveryCode($user, $fortress, $code);

        if ($consumed) {
            return TwoFactorChallengeVerificationResult::recoveryCodeValid();
        }

        return TwoFactorChallengeVerificationResult::invalid();
    }

    protected function replayCacheKey(Model $user, Fortress $fortress): string
    {
        return implode(':', ['guardian:two-factor:totp-last-used', $fortress->getId(), $user::class, $user->getKey()]);
    }
}
