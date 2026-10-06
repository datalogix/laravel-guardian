<?php

namespace Datalogix\Guardian\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * A model signed in by a fortress whose table lives on a connection other than the default.
 */
class OtherConnectionAdmin extends Model
{
    protected $connection = 'admins';

    protected $table = 'admins';
}
