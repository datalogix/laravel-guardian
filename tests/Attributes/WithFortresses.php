<?php

namespace Datalogix\Guardian\Tests\Attributes;

use Attribute;

/**
 * For a single test: the fortresses returned by the given method.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class WithFortresses
{
    public function __construct(public string $method) {}
}
