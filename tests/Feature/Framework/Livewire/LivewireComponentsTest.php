<?php

namespace Datalogix\Guardian\Tests\Feature\Framework\Livewire;

use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Framework\Livewire\ComponentCache;
use Datalogix\Guardian\Framework\Livewire\LivewireAdapter;
use Datalogix\Guardian\Framework\Livewire\Pages\Login;
use Datalogix\Guardian\Framework\Livewire\Pages\OAuthCompleteRegistration;
use Datalogix\Guardian\Http\Controllers\OAuthController;
use Datalogix\Guardian\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use ReflectionProperty;

#[Group('livewire')]
class LivewireComponentsTest extends TestCase
{
    public function test_the_cache_path_is_namespaced_by_fortress_id(): void
    {
        $fortress = Fortress::make()->id('example');

        $this->assertStringEndsWith('example.php', (new LivewireAdapter)->componentCachePath($fortress));
    }

    public function test_components_are_discovered_from_the_enabled_features(): void
    {
        $fortress = Fortress::make()->livewire()->basic('pages')->oauth(providers: ['github']);

        $components = (new LivewireAdapter)->components($fortress);

        $this->assertContains(Login::class, $components);
        $this->assertContains(OAuthCompleteRegistration::class, $components);
    }

    public function test_route_actions_that_are_not_livewire_components_are_ignored(): void
    {
        $fortress = Fortress::make()->livewire()->id('mixed')
            ->login(routeAction: fn () => 'a closure')
            ->signUp(routeAction: OAuthController::class)
            ->passwordReset(
                forgotPasswordRouteAction: [OAuthController::class, 'redirect'],
                resetPasswordRouteAction: false,
            )
            ->logout();

        $this->assertSame([], (new LivewireAdapter)->components($fortress));
    }

    public function test_cache_components_writes_a_readable_php_cache_file(): void
    {
        $adapter = new LivewireAdapter;
        $fortress = Fortress::make()->livewire()->basic('example');

        $adapter->cacheComponents($fortress);

        $this->assertFileExists($adapter->componentCachePath($fortress));

        $cache = require $adapter->componentCachePath($fortress);

        $this->assertArrayHasKey('livewireComponents', $cache);
        $this->assertContains(Login::class, $cache['livewireComponents']);
    }

    public function test_the_cache_is_ignored_while_running_in_console_even_if_a_file_exists(): void
    {
        // Artisan and tests always see the components on disk.
        $fortress = Fortress::make()->livewire()->basic('example');
        (new LivewireAdapter)->cacheComponents($fortress);

        $this->assertTrue(app()->runningInConsole());
        $this->assertFalse((new ComponentCache)->exists($fortress));
    }

    public function test_the_cache_is_used_by_real_requests_once_it_is_written(): void
    {
        $fortress = Fortress::make()->livewire()->basic('example');

        // runningInConsole() is memoized, and this test suite always runs in the console.
        $property = new ReflectionProperty($this->app, 'isRunningInConsole');
        $property->setValue($this->app, false);

        $this->assertFalse((new ComponentCache)->exists($fortress));

        (new LivewireAdapter)->cacheComponents($fortress);

        $this->assertTrue((new ComponentCache)->exists($fortress));
    }

    public function test_components_come_from_the_cache_when_it_can_be_used(): void
    {
        $fortress = Fortress::make()->livewire()->basic('cached');
        $cache = new class extends ComponentCache
        {
            public function exists(Fortress $fortress): bool
            {
                return true;
            }
        };

        $cache->write($fortress, ['my-login' => Login::class]);

        $this->assertSame(['my-login' => Login::class], (new LivewireAdapter($cache))->components($fortress));
    }

    public function test_clear_cached_components_removes_the_file(): void
    {
        $adapter = new LivewireAdapter;
        $fortress = Fortress::make()->livewire()->basic('example');
        $adapter->cacheComponents($fortress);

        $adapter->clearCachedComponents($fortress);

        $this->assertFileDoesNotExist($adapter->componentCachePath($fortress));
    }
}
