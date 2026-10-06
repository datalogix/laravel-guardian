<?php

namespace Datalogix\Guardian\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * Fully guarded, to force a MassAssignmentException.
 */
class GuardedUser extends Model
{
    protected $table = 'users';
}
