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
    use Concerns\HasRateLimiter;

    public function __construct(
        protected TwoFactorChallengeVerifier $challengeVerifier,
        protected PostAuthenticationFlow $postAuthenticationFlow,
    ) {}

    public function __invoke(array $data = []): AuthFlowResult
    {
        $challenge = Guardian::getTwoFactorChallengeSession();

        if (! $challenge) {
            throw TwoFactorChallengeException::notPending();
        }

        $throttleKey = $this->throttleKey((string) ($challenge['user_id'] ?? null), includeIp: false);
        $maxAttempts = Guardian::getTwoFactorChallengeFeature()->getMaxAttempts();

        $this->ensureIsNotRateLimited(
            $throttleKey,
            $maxAttempts,
            function (int $seconds) {
                $user = Guardian::getPendingTwoFactorChallengeUser();

                event(new TwoFactorChallengeFailed(
                    Guardian::getCurrentOrDefaultFortress(),
                    $user instanceof Model ? $user : null,
                    'rate-limited',
                ));

                throw TwoFactorChallengeException::rateLimited($seconds);
            }
        );

        $rememberDevice = (bool) ($data['remember_device'] ?? false);
        Guardian::setTwoFactorChallengeRememberDevice($rememberDevice);

        $user = Guardian::getPendingTwoFactorChallengeUser();

        if (! $user instanceof Model) {
            Guardian::clearTwoFactorChallenge();
            event(new TwoFactorChallengeFailed(Guardian::getCurrentOrDefaultFortress(), null, 'not-pending'));

            throw TwoFactorChallengeException::notPending();
        }

        $fortress = Guardian::getCurrentOrDefaultFortress();
        $code = (string) ($data['code'] ?? '');
        $verification = $this->timeboxedVerify($user, $fortress, $code);
        $usedRecoveryCode = $verification->usedRecoveryCode();

        if (! $verification->isValid()) {
            $this->hitRateLimiterIfThrottled($throttleKey, $maxAttempts);
            event(new TwoFactorChallengeFailed($fortress, $user, 'invalid-code'));

            throw TwoFactorChallengeException::invalid();
        }

        if ($usedRecoveryCode) {
            event(new TwoFactorRecoveryCodeUsed($fortress, $user));
        }

        $this->clearRateLimiterIfThrottled($throttleKey, $maxAttempts);

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
