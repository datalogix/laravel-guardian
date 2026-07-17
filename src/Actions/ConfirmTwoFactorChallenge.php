<?php

namespace Datalogix\Guardian\Actions;

use Datalogix\Guardian\Actions\Contracts\HasValidationRules;
use Datalogix\Guardian\Events\TwoFactorChallengeFailed;
use Datalogix\Guardian\Events\TwoFactorChallengeSucceeded;
use Datalogix\Guardian\Events\TwoFactorRecoveryCodeUsed;
use Datalogix\Guardian\Exceptions\TwoFactorChallengeException;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Support\TwoFactor\TwoFactorChallengeVerifier;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;

class ConfirmTwoFactorChallenge implements HasValidationRules
{
    use Concerns\HasRateLimiter;

    public function __construct(
        protected TwoFactorChallengeVerifier $challengeVerifier,
    ) {}

    public function __invoke(array $data = []): void
    {
        $challenge = Guardian::getTwoFactorChallengeSession();

        if (! $challenge) {
            throw TwoFactorChallengeException::notPending();
        }

        $throttleKey = $this->throttleKey((string) ($challenge['user_id'] ?? null));
        $this->ensureIsNotRateLimited($throttleKey);

        Guardian::setTwoFactorChallengeRememberDevice((bool) ($data['remember_device'] ?? false));

        $user = Guardian::getPendingTwoFactorChallengeUser();

        if (! $user instanceof Model) {
            Guardian::clearTwoFactorChallenge();
            event(new TwoFactorChallengeFailed(Guardian::getCurrentOrDefaultFortress(), null, 'not-pending'));

            throw TwoFactorChallengeException::notPending();
        }

        $fortress = Guardian::getCurrentOrDefaultFortress();
        $code = (string) ($data['code'] ?? '');
        $verification = $this->challengeVerifier->verify($user, $fortress, $code);
        $usedRecoveryCode = $verification->usedRecoveryCode();

        if (! $verification->isValid()) {
            RateLimiter::hit($throttleKey);
            event(new TwoFactorChallengeFailed($fortress, $user, 'invalid-code'));

            throw TwoFactorChallengeException::invalid();
        }

        if ($usedRecoveryCode) {
            event(new TwoFactorRecoveryCodeUsed($fortress, $user));
        }

        RateLimiter::clear($throttleKey);

        Guardian::auth()->login($user, Guardian::getTwoFactorChallengeRemember());
        Guardian::clearPendingTwoFactorSetup();

        if ((bool) ($data['remember_device'] ?? false)) {
            Guardian::rememberTwoFactorOnCurrentDevice($user);
        }

        Guardian::clearTwoFactorChallenge();

        event(new TwoFactorChallengeSucceeded($fortress, $user, $usedRecoveryCode));

        Session::regenerate();
    }

    public static function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:64'],
            'remember_device' => ['nullable', 'boolean'],
        ];
    }

    protected function ensureIsNotRateLimited(string $throttleKey): void
    {
        $maxAttempts = Guardian::getTwoFactorChallengeFeature()->getMaxAttempts();

        if (! $this->shouldThrottle($maxAttempts)) {
            return;
        }

        if (! RateLimiter::tooManyAttempts($throttleKey, $maxAttempts)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($throttleKey);

        $user = Guardian::getPendingTwoFactorChallengeUser();

        event(new TwoFactorChallengeFailed(
            Guardian::getCurrentOrDefaultFortress(),
            $user instanceof Model ? $user : null,
            'rate-limited',
        ));

        throw TwoFactorChallengeException::rateLimited($seconds);
    }
}
