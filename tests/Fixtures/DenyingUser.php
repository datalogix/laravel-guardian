<?php

namespace Datalogix\Guardian\Tests\Fixtures;

use Datalogix\Guardian\Fortress;

/**
 * A user no fortress lets in.
 */
class DenyingUser extends User
{
    protected $table = 'users';

    public function canAccessFortress(Fortress $fortress): bool
    {
        return false;
    }
}
