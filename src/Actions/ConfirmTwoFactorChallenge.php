<?php

namespace Datalogix\Guardian\Actions;

use Datalogix\Guardian\Actions\Contracts\HasValidationRules;
use Datalogix\Guardian\Events\TwoFactorChallengeFailed;
use Datalogix\Guardian\Events\TwoFactorChallengeSucceeded;
use Datalogix\Guardian\Events\TwoFactorRecoveryCodeUsed;
use Datalogix\Guardian\Exceptions\TwoFactorChallengeException;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Support\Totp;
use Datalogix\Guardian\Support\TwoFactorUser;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;

class ConfirmTwoFactorChallenge implements HasValidationRules
{
    use Concerns\HasRateLimiter;

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

        if (! $user instanceof \Illuminate\Database\Eloquent\Model) {
            Guardian::clearTwoFactorChallenge();
            event(new TwoFactorChallengeFailed(Guardian::getCurrentOrDefaultFortress(), null, 'not-pending'));

            throw TwoFactorChallengeException::notPending();
        }

        $fortress = Guardian::getCurrentOrDefaultFortress();
        $manager = app(TwoFactorUser::class);
        $secret = $manager->getTwoFactorSecret($user, $fortress);
        $code = (string) ($data['code'] ?? '');
        $isTotpValid = is_string($secret) && app(Totp::class)->verify($secret, $code);

        $usedRecoveryCode = ! $isTotpValid && $this->consumeRecoveryCode($user, $code);

        if (! $isTotpValid && ! $usedRecoveryCode) {
            RateLimiter::hit($throttleKey);
            event(new TwoFactorChallengeFailed($fortress, $user, 'invalid-code'));

            throw TwoFactorChallengeException::invalid();
        }

        RateLimiter::clear($throttleKey);

        Guardian::auth()->login($user, Guardian::getTwoFactorChallengeRemember());

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
            $user instanceof \Illuminate\Database\Eloquent\Model ? $user : null,
            'rate-limited',
        ));

        throw TwoFactorChallengeException::rateLimited($seconds);
    }

    protected function consumeRecoveryCode(object $user, string $code): bool
    {
        $manager = app(TwoFactorUser::class);
        $fortress = Guardian::getCurrentOrDefaultFortress();
        $consumed = $manager->consumeTwoFactorRecoveryCode($user, $fortress, $code);

        if ($consumed && $user instanceof \Illuminate\Database\Eloquent\Model) {
            event(new TwoFactorRecoveryCodeUsed($fortress, $user));
        }

        return $consumed;
    }
}
