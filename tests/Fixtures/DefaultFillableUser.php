<?php

namespace Datalogix\Guardian\Tests\Fixtures;

/**
 * A user model with the $fillable of a new Laravel application.
 */
class DefaultFillableUser extends User
{
    protected $table = 'users';

    protected $fillable = ['name', 'email', 'password'];

    protected $guarded = ['*'];
}
