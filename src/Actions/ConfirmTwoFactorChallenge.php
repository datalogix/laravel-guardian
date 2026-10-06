<?php

namespace Datalogix\Guardian\Actions;

use Datalogix\Guardian\Actions\Contracts\HasValidationRules;
use Datalogix\Guardian\Enums\AuthFlowResult;
use Datalogix\Guardian\Events\TwoFactorChallengeFailed;
use Datalogix\Guardian\Events\TwoFactorChallengeSucceeded;
use Datalogix\Guardian\Events\TwoFactorRecoveryCodeUsed;
use Datalogix\Guardian\Exceptions\TwoFactorChallengeException;
use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Support\Auth\PostAuthenticationFlow;
use Datalogix\Guardian\Support\TwoFactor\TwoFactorChallengeVerificationResult;
use Datalogix\Guardian\Support\TwoFactor\TwoFactorChallengeVerifier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Timebox;

class ConfirmTwoFactorChallenge implements HasValidationRules
{
    /**
     * Wrong codes a user may enter in a day, whatever the limit per minute: without
     * it, whoever has the password could keep guessing at that pace for as long as
     * they like.
     */
    public const DAILY_MAX_ATTEMPTS = 20;

    use Concerns\HasRateLimiter;

    public function __construct(
        protected TwoFactorChallengeVerifier $challengeVerifier,
        protected PostAuthenticationFlow $postAuthenticationFlow,
    ) {
        //
    }

    public function __invoke(array $data = []): AuthFlowResult
    {
        $challenge = Guardian::getTwoFactorChallengeSession();

        if (! $challenge) {
            throw TwoFactorChallengeException::notPending();
        }

        $fortress = Guardian::getCurrentOrDefaultFortress();
        $userKey = (string) ($challenge['user_id'] ?? null);
        $throttleKey = $this->throttleKey($userKey, includeIp: false);
        $dailyThrottleKey = $this->throttleKey('daily|'.$userKey, includeIp: false);
        $maxAttempts = Guardian::getTwoFactorChallengeFeature()->getMaxAttempts();
        $dailyMaxAttempts = $this->shouldThrottle($maxAttempts) ? static::DAILY_MAX_ATTEMPTS : false;

        $onLockout = function (int $seconds) {
            $user = Guardian::getPendingTwoFactorChallengeUser();

            event(new TwoFactorChallengeFailed(
                Guardian::getCurrentOrDefaultFortress(),
                $user instanceof Model ? $user : null,
                'rate-limited',
            ));

            throw TwoFactorChallengeException::rateLimited($seconds);
        };

        $this->reserveAttempt($throttleKey, $maxAttempts, $onLockout);
        $this->reserveAttempt($dailyThrottleKey, $dailyMaxAttempts, $onLockout, 86400);

        $rememberDevice = (bool) ($data['remember_device'] ?? false);
        Guardian::setTwoFactorChallengeRememberDevice($rememberDevice);

        $user = Guardian::getPendingTwoFactorChallengeUser();

        if (! $user instanceof Model) {
            Guardian::clearTwoFactorChallenge();
            event(new TwoFactorChallengeFailed(Guardian::getCurrentOrDefaultFortress(), null, 'not-pending'));

            throw TwoFactorChallengeException::notPending();
        }

        $code = (string) ($data['code'] ?? '');
        $verification = $this->timeboxedVerify($user, $fortress, $code);
        $usedRecoveryCode = $verification->usedRecoveryCode();

        if (! $verification->isValid()) {
            event(new TwoFactorChallengeFailed($fortress, $user, 'invalid-code'));

            throw TwoFactorChallengeException::invalid();
        }

        if ($usedRecoveryCode) {
            event(new TwoFactorRecoveryCodeUsed($fortress, $user));
        }

        $this->clearRateLimiterIfThrottled($throttleKey, $maxAttempts);
        $this->clearRateLimiterIfThrottled($dailyThrottleKey, $dailyMaxAttempts);

        if ($rememberDevice) {
            Guardian::rememberTwoFactorOnCurrentDevice($user);
        }

        $this->postAuthenticationFlow->finalize($user, Guardian::getTwoFactorChallengeRemember());

        event(new TwoFactorChallengeSucceeded($fortress, $user, $usedRecoveryCode));

        return AuthFlowResult::Authenticated;
    }

    public static function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:64'],
            'remember_device' => ['nullable', 'boolean'],
        ];
    }

    protected function timeboxedVerify(Model $user, Fortress $fortress, string $code): TwoFactorChallengeVerificationResult
    {
        return app(Timebox::class)->call(function ($timebox) use ($user, $fortress, $code) {
            $result = $this->challengeVerifier->verify($user, $fortress, $code);

            $timebox->returnEarly();

            return $result;
        }, config('auth.timebox_duration', 200000));
    }
}
