<?php

namespace Datalogix\Guardian\Tests\Fixtures;

/**
 * A model like the ones of apps that predate the "hashed" cast: it stores the
 * password exactly as it is given.
 */
class PlainPasswordUser extends User
{
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'can_access' => 'boolean',
        ];
    }
}
