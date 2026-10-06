<?php

namespace Datalogix\Guardian\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * A totally-guarded model (no $fillable, default $guarded = ['*']) used only to
 * force Illuminate\Database\Eloquent\MassAssignmentException from mass
 * assignment, exercising CreatesAuthenticatableUser's catch branch for it.
 */
class GuardedUser extends Model
{
    protected $table = 'users';
}
