<?php

namespace Datalogix\Guardian\Tests\Feature\Concerns;

use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Http\Middleware\DispatchServingGuardianEvent;
use Datalogix\Guardian\Http\Middleware\SetUpFortress;
use Datalogix\Guardian\Tests\TestCase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Group;

class HasMiddlewareTest extends TestCase
{
    public function test_get_middleware_always_includes_the_set_up_fortress_middleware_first(): void
    {
        $fortress = Fortress::make()->id('example');

        $this->assertSame([SetUpFortress::class.':example', 'web'], $fortress->getMiddleware());
    }

    public function test_the_global_middleware_comes_from_the_config(): void
    {
        config(['guardian.middleware' => ['web', 'custom-global']]);

        $this->assertSame(
            [SetUpFortress::class.':example', 'web', 'custom-global'],
            Fortress::make()->id('example')->getMiddleware()
        );
    }

    public function test_the_global_middleware_can_be_left_out(): void
    {
        config(['guardian.middleware' => []]);

        $this->assertSame([SetUpFortress::class.':example'], Fortress::make()->id('example')->getMiddleware());
    }

    public function test_presets_keep_their_middleware_order(): void
    {
        $this->assertSame(
            [SetUpFortress::class.':default', 'web', DispatchServingGuardianEvent::class],
            Fortress::make()->basic()->getMiddleware()
        );
    }

    public function test_middleware_appends_to_the_route_middleware_stack(): void
    {
        // "web" is already given by the config, so it is not repeated.
        $fortress = Fortress::make()->id('example')->middleware(['web'])->middleware(['throttle:10,1']);

        $this->assertSame(
            [SetUpFortress::class.':example', 'web', 'throttle:10,1'],
            $fortress->getMiddleware()
        );
    }

    public function test_without_middleware_leaves_the_global_middleware_out_of_one_fortress(): void
    {
        config(['guardian.middleware' => ['web', 'custom-global']]);

        $admin = Fortress::make()->id('admin')->withoutMiddleware(['web'])->middleware(['admin-web']);
        $other = Fortress::make()->id('other');

        $this->assertSame([SetUpFortress::class.':admin', 'custom-global', 'admin-web'], $admin->getMiddleware());
        $this->assertSame([SetUpFortress::class.':other', 'web', 'custom-global'], $other->getMiddleware());
    }

    public function test_without_middleware_also_leaves_out_the_middleware_of_the_fortress(): void
    {
        $fortress = Fortress::make()->basic()->withoutMiddleware([DispatchServingGuardianEvent::class]);

        $this->assertSame([SetUpFortress::class.':default', 'web'], $fortress->getMiddleware());
    }

    public function test_without_middleware_accumulates(): void
    {
        config(['guardian.middleware' => ['web', 'custom-global']]);

        $fortress = Fortress::make()->id('example')->withoutMiddleware(['web'])->withoutMiddleware(['custom-global']);

        $this->assertSame([SetUpFortress::class.':example'], $fortress->getMiddleware());
    }

    public function test_the_set_up_fortress_middleware_cannot_be_left_out(): void
    {
        $fortress = Fortress::make()->id('example')->withoutMiddleware([SetUpFortress::class.':example', 'web']);

        $this->assertSame([SetUpFortress::class.':example'], $fortress->getMiddleware());
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
