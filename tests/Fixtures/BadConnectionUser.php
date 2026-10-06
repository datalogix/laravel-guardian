<?php

namespace Datalogix\Guardian\Tests\Fixtures;

/**
 * Points at an unconfigured connection, so schema inspection throws.
 */
class BadConnectionUser extends User
{
    protected $connection = 'not-a-configured-connection';
}
