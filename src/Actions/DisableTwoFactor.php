<?php

namespace Datalogix\Guardian\Actions;

use Datalogix\Guardian\Events\TwoFactorDisabled;
use Datalogix\Guardian\Exceptions\PasswordConfirmationException;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Support\TwoFactorUser;
use Illuminate\Support\Facades\Session;

class DisableTwoFactor
{
    public function __invoke(object $user): void
    {
        if (! $this->passwordWasRecentlyConfirmed()) {
            throw PasswordConfirmationException::requiredForDisablingTwoFactor();
        }

        $fortress = Guardian::getCurrentOrDefaultFortress();
        $manager = app(TwoFactorUser::class);

        $manager->saveTwoFactorSecret($user, $fortress, null);
        $manager->saveTwoFactorRecoveryCodes($user, $fortress, []);

        if ($user instanceof \Illuminate\Database\Eloquent\Model) {
            Guardian::revokeAllTrustedTwoFactorDevices($user);
            event(new TwoFactorDisabled($fortress, $user));
        }

        Guardian::clearTwoFactorSetup();
        Guardian::clearTwoFactorChallenge();
        Guardian::forgetRememberedTwoFactorDevice();
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
