<?php

namespace Datalogix\Guardian\Actions;

use Datalogix\Guardian\Events\TwoFactorRecoveryCodesRegenerated;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Support\RecoveryCodes;
use Datalogix\Guardian\Support\TwoFactorUser;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;

class RegenerateTwoFactorRecoveryCodes
{
    /**
     * @return array<int, string>
     */
    public function __invoke(object $user): array
    {
        if (! $this->passwordWasRecentlyConfirmed()) {
            throw ValidationException::withMessages([
                'password' => [__('Please confirm your password before regenerating two-factor recovery codes.')],
            ]);
        }

        $manager = app(TwoFactorUser::class);

        if (! $manager->canStoreTwoFactorRecoveryCodes($user)) {
            return [];
        }

        $codes = app(RecoveryCodes::class)->generate();

        $manager->saveTwoFactorRecoveryCodes($user, Guardian::getCurrentOrDefaultFortress(), $codes);

        if ($user instanceof \Illuminate\Database\Eloquent\Model) {
            event(new TwoFactorRecoveryCodesRegenerated(Guardian::getCurrentOrDefaultFortress(), $user, count($codes)));
        }

        return $codes;
    }

    protected function passwordWasRecentlyConfirmed(): bool
    {
        $confirmedAt = Session::get('auth.password_confirmed_at');

        if (! is_numeric($confirmedAt)) {
            return false;
        }

        $timeout = (int) config('auth.password_timeout', 10800);

        return ((int) $confirmedAt + $timeout) > time();
    }
}
