<?php

namespace Datalogix\Guardian\Actions;

use Datalogix\Guardian\Actions\Contracts\HasValidationRules;
use Datalogix\Guardian\Events\TwoFactorEnabled;
use Datalogix\Guardian\Exceptions\TwoFactorSetupException;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Support\RecoveryCodes;
use Datalogix\Guardian\Support\Totp;
use Datalogix\Guardian\Support\TwoFactorUser;

class EnableTwoFactor implements HasValidationRules
{
    /**
     * @return array<int, string>
     */
    public function __invoke(object $user, array $data = []): array
    {
        $pendingSecret = Guardian::getTwoFactorSetupSecret();

        if (! is_string($pendingSecret) || blank($pendingSecret)) {
            throw TwoFactorSetupException::missingPendingSecret();
        }

        if (! app(Totp::class)->verify($pendingSecret, $data['code'] ?? '')) {
            throw TwoFactorSetupException::invalidCode();
        }

        $fortress = Guardian::getCurrentOrDefaultFortress();
        $manager = app(TwoFactorUser::class);

        if (! $manager->canStoreTwoFactorSecret($user) || ! $manager->saveTwoFactorSecret($user, $fortress, $pendingSecret)) {
            Guardian::clearTwoFactorSetup();

            return [];
        }

        $recoveryCodes = [];

        if ($manager->canStoreTwoFactorRecoveryCodes($user)) {
            $recoveryCodes = app(RecoveryCodes::class)->generate();
            $manager->saveTwoFactorRecoveryCodes($user, $fortress, $recoveryCodes);
        }

        Guardian::clearTwoFactorSetup();

        if ($user instanceof \Illuminate\Database\Eloquent\Model) {
            event(new TwoFactorEnabled($fortress, $user));
        }

        return $recoveryCodes;
    }

    public static function rules(): array
    {
        return [
            'code' => ['required', 'string', 'regex:/^\d{6}$/'],
        ];
    }
}
