<?php

namespace Datalogix\Guardian\Tests\Feature\Concerns;

use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Tests\TestCase;

class HasLifecycleHooksTest extends TestCase
{
    public function test_boot_using_callbacks_run_when_the_fortress_boots(): void
    {
        $calls = [];

        $fortress = Fortress::make()
            ->bootUsing(function (Fortress $fortress) use (&$calls) {
                $calls[] = 'first:'.$fortress->getId();
            })
            ->id('example')
            ->bootUsing(function (Fortress $fortress) use (&$calls) {
                $calls[] = 'second:'.$fortress->getId();
            });

        $fortress->boot();

        $this->assertSame(['first:example', 'second:example'], $calls);
    }

    public function test_boot_does_nothing_without_registered_callbacks(): void
    {
        $this->expectNotToPerformAssertions();

        Fortress::make()->boot();
    }
}
