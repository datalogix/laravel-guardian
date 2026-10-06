<?php

namespace Datalogix\Guardian\Tests\Feature\Framework\Livewire;

use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Framework\Livewire\ComponentCache;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Tests\Attributes\WithFortresses;
use Datalogix\Guardian\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('livewire')]
class ComponentCacheCommandsTest extends TestCase
{
    protected function componentCachePath(): string
    {
        $fortress = Guardian::getDefaultFortress();

        return $fortress->getFrameworkAdapter()->componentCachePath($fortress);
    }

    public function test_cache_components_writes_the_cache_file(): void
    {
        $this->artisan('guardian:cache-components')->assertSuccessful();

        $this->assertFileExists($this->componentCachePath());
    }

    public function test_clear_cached_components_removes_the_cache_file(): void
    {
        $this->artisan('guardian:cache-components')->assertSuccessful();
        $this->assertFileExists($this->componentCachePath());

        $this->artisan('guardian:clear-cached-components')->assertSuccessful();

        $this->assertFileDoesNotExist($this->componentCachePath());
    }

    protected function usingInertia(): array
    {
        return [Fortress::make()->inertia()->basic()];
    }

    #[Group('inertia')]
    #[WithFortresses('usingInertia')]
    public function test_frameworks_without_a_component_cache_are_skipped(): void
    {
        $this->artisan('guardian:cache-components')->assertSuccessful();
        $this->artisan('guardian:clear-cached-components')->assertSuccessful();

        $this->assertFileDoesNotExist((new ComponentCache)->path(Guardian::getDefaultFortress()));
    }
}
