<?php

namespace Datalogix\Guardian\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * A user model that does not implement MustVerifyEmail.
 */
class UserWithoutEmailVerification extends Authenticatable
{
    protected $table = 'users';

    protected $guarded = [];
}
