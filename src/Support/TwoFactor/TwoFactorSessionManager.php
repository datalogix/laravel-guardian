<?php

namespace Datalogix\Guardian\Support\TwoFactor;

use Datalogix\Guardian\Enums\TwoFactorMethod;
use Datalogix\Guardian\Fortress;
use Illuminate\Database\Eloquent\Model;

class TwoFactorSessionManager
{
    public function __construct(
        protected TwoFactorSessionState $state,
    ) {}

    public function startChallenge(Fortress $fortress, Model $user, bool $remember, TwoFactorMethod $method): void
    {
        $this->state->put($fortress->getTwoFactorChallengeSessionKey(), [
            'user_id' => $user->getAuthIdentifier(),
            'remember' => $remember,
            'remember_device' => false,
            'guard' => $fortress->getGuard(),
            'method' => $method->value,
            'started_at' => now()->timestamp,
        ]);
    }

    public function getChallenge(Fortress $fortress): ?array
    {
        return $this->state->getValid($fortress->getTwoFactorChallengeSessionKey(), $fortress->getTwoFactorChallengeTtl());
    }

    public function clearChallenge(Fortress $fortress): void
    {
        $this->state->forget($fortress->getTwoFactorChallengeSessionKey());
    }

    public function updateChallenge(Fortress $fortress, callable $mutator): ?array
    {
        return $this->state->update($fortress->getTwoFactorChallengeSessionKey(), $mutator);
    }

    public function startSetup(Fortress $fortress, string $secret, TwoFactorMethod $method): void
    {
        $this->state->put($fortress->getTwoFactorSetupSessionKey(), [
            'secret' => $secret,
            'method' => $method->value,
            'started_at' => now()->timestamp,
        ]);
    }

    public function getSetup(Fortress $fortress): ?array
    {
        return $this->state->getValid($fortress->getTwoFactorSetupSessionKey(), $fortress->getTwoFactorSetupTtl());
    }

    public function clearSetup(Fortress $fortress): void
    {
        $this->state->forget($fortress->getTwoFactorSetupSessionKey());
    }

    public function startPendingSetup(Fortress $fortress, Model $user, bool $remember, TwoFactorMethod $method): void
    {
        $this->state->put($fortress->getPendingTwoFactorSetupSessionKey(), [
            'user_id' => $user->getAuthIdentifier(),
            'remember' => $remember,
            'guard' => $fortress->getGuard(),
            'method' => $method->value,
            'started_at' => now()->timestamp,
        ]);
    }

    public function getPendingSetup(Fortress $fortress): ?array
    {
        return $this->state->getValid($fortress->getPendingTwoFactorSetupSessionKey(), $fortress->getTwoFactorSetupTtl());
    }

    public function clearPendingSetup(Fortress $fortress): void
    {
        $this->state->forget($fortress->getPendingTwoFactorSetupSessionKey());
    }
}
