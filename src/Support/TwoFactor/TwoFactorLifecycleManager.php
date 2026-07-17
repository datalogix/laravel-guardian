<?php

namespace Datalogix\Guardian\Support\TwoFactor;

use Datalogix\Guardian\Events\TwoFactorDisabled;
use Datalogix\Guardian\Events\TwoFactorRecoveryCodesRegenerated;
use Datalogix\Guardian\Guardian;
use Illuminate\Database\Eloquent\Model;

class TwoFactorLifecycleManager
{
    public function __construct(
        protected TwoFactorUser $twoFactorUser,
        protected RecoveryCodes $recoveryCodes,
    ) {}

    public function disable(object $user): void
    {
        $fortress = Guardian::getCurrentOrDefaultFortress();

        $this->twoFactorUser->saveTwoFactorSecret($user, $fortress, null);
        $this->twoFactorUser->saveTwoFactorRecoveryCodes($user, $fortress, []);

        if ($user instanceof Model) {
            Guardian::revokeAllTrustedTwoFactorDevices($user);
            event(new TwoFactorDisabled($fortress, $user));
        }

        Guardian::clearTwoFactorSetup();
        Guardian::clearTwoFactorChallenge();
        Guardian::forgetRememberedTwoFactorDevice();
    }

    /**
     * @return array<int, string>
     */
    public function regenerateRecoveryCodes(object $user): array
    {
        if (! $this->twoFactorUser->canStoreTwoFactorRecoveryCodes($user)) {
            return [];
        }

        $codes = $this->recoveryCodes->generate();
        $fortress = Guardian::getCurrentOrDefaultFortress();

        $this->twoFactorUser->saveTwoFactorRecoveryCodes($user, $fortress, $codes);

        if ($user instanceof Model) {
            event(new TwoFactorRecoveryCodesRegenerated($fortress, $user, count($codes)));
        }

        return $codes;
    }
}
