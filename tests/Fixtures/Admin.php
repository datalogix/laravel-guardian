<?php

namespace Datalogix\Guardian\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * The model of a second guard, with a table of its own.
 */
class Admin extends Authenticatable
{
    protected $table = 'admins';

    protected $guarded = [];
}
