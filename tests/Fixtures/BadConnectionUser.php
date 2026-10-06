<?php

namespace Datalogix\Guardian\Tests\Fixtures;

/**
 * Points at a database connection that isn't configured, to force
 * TwoFactorUser::hasColumn()'s catch(Throwable) branch when Schema inspection
 * itself fails, instead of merely finding no matching column.
 */
class BadConnectionUser extends User
{
    protected $connection = 'not-a-configured-connection';
}
