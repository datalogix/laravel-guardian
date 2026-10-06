<?php

namespace Datalogix\Guardian\Tests\Feature\Concerns;

use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Http\Middleware\SetUpFortress;
use Datalogix\Guardian\Tests\TestCase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Group;

class HasMiddlewareTest extends TestCase
{
    public function test_get_middleware_always_includes_the_set_up_fortress_middleware_first(): void
    {
        $fortress = Fortress::make()->id('example');

        $this->assertSame([SetUpFortress::class.':example'], $fortress->getMiddleware());
    }

    public function test_middleware_appends_to_the_route_middleware_stack(): void
    {
        $fortress = Fortress::make()->id('example')->middleware(['web'])->middleware(['throttle:10,1']);

        $this->assertSame(
            [SetUpFortress::class.':example', 'web', 'throttle:10,1'],
            $fortress->getMiddleware()
        );
    }

    public function test_auth_middleware_is_tracked_separately(): void
    {
        $fortress = Fortress::make()->authMiddleware(['auth:web']);

        $this->assertSame(['auth:web'], $fortress->getAuthMiddleware());
    }

    #[Group('livewire')]
    public function test_middleware_with_is_persistent_also_registers_it_with_livewire_on_register(): void
    {
        $fortress = Fortress::make()->id('persistent-example')
            ->middleware(['custom-persistent'], isPersistent: true);

        $fortress->register();

        $this->assertContains('custom-persistent', Livewire::getPersistentMiddleware());
        // isPersistent: true still appends to the regular route middleware stack too.
        $this->assertContains('custom-persistent', $fortress->getMiddleware());
    }
}
