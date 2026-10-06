<?php

namespace Datalogix\Guardian\Tests\Attributes;

use Attribute;

/**
 * Registers the fortresses returned by the given method of the test case
 * instead of the ones returned by fortresses(), for a single test.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class WithFortresses
{
    public function __construct(public string $method)
    {
        //
    }
}
