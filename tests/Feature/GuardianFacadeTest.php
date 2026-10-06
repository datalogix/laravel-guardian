<?php

namespace Datalogix\Guardian\Tests\Feature;

use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\GuardianManager;
use Datalogix\Guardian\Tests\TestCase;

class GuardianFacadeTest extends TestCase
{
    public function test_facade_resolves_to_the_guardian_manager(): void
    {
        $this->assertInstanceOf(GuardianManager::class, Guardian::getFacadeRoot());
    }

    public function test_facade_forwards_manager_methods(): void
    {
        $this->assertCount(1, Guardian::getFortresses());
    }

    public function test_facade_forwards_mixed_in_fortress_methods(): void
    {
        $this->assertSame('default', Guardian::getId());
        $this->assertTrue(Guardian::getLoginFeature()->hasFeature());
    }
}
