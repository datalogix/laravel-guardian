<?php

namespace Datalogix\Guardian\Tests\Fixtures;

use Datalogix\Guardian\Contracts\CanManageTwoFactorAuthentication;
use Datalogix\Guardian\Contracts\TwoFactorAuthenticatable;
use Datalogix\Guardian\Fortress;
use Illuminate\Database\Eloquent\Model;

/**
 * A model that reads and saves its two-factor secret itself, and leaves the
 * recovery codes to Guardian.
 */
class SecretManagingAdmin extends Model implements CanManageTwoFactorAuthentication, TwoFactorAuthenticatable
{
    protected $table = 'secret_managing_admins';

    public function hasTwoFactorEnabled(Fortress $fortress): bool
    {
        return false;
    }

    public function getTwoFactorSecret(Fortress $fortress): ?string
    {
        return null;
    }

    public function saveTwoFactorSecret(Fortress $fortress, ?string $secret): void {}
}
