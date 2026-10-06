<?php

namespace Datalogix\Guardian\Tests\Feature\Framework\Livewire;

use Datalogix\Guardian\Framework\FrameworkResolver;
use Datalogix\Guardian\Framework\Livewire\LivewireAdapter;
use Datalogix\Guardian\Framework\Livewire\LivewireServiceProvider;
use Datalogix\Guardian\Http\Middleware\Authenticate;
use Datalogix\Guardian\Http\Middleware\DispatchServingGuardianEvent;
use Datalogix\Guardian\Http\Middleware\SetUpFortress;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Group;

class LivewireServiceProviderTest extends TestCase
{
    public function test_the_adapter_is_registered_in_the_framework_resolver(): void
    {
        $adapter = app(FrameworkResolver::class)->adapters()['livewire'];

        $this->assertInstanceOf(LivewireAdapter::class, $adapter);
        $this->assertSame($adapter, app(LivewireAdapter::class));
    }

    public function test_the_views_are_loaded_under_the_guardian_namespace(): void
    {
        $this->assertTrue(view()->exists('guardian::login'));
        $this->assertTrue(view()->exists('guardian::layouts.simple'));
        $this->assertTrue(view()->exists('guardian::layouts.split'));
    }

    public function test_the_views_are_published(): void
    {
        $paths = ServiceProvider::pathsToPublish(LivewireServiceProvider::class, 'guardian-views');

        $this->assertNotEmpty($paths);
        $this->assertStringEndsWith('Framework/Livewire/resources/views', array_key_first($paths));
        $this->assertStringEndsWith('views/vendor/guardian', array_values($paths)[0]);
    }

    public function test_the_component_cache_commands_are_registered(): void
    {
        $commands = array_keys(Artisan::all());

        $this->assertContains('guardian:cache-components', $commands);
        $this->assertContains('guardian:clear-cached-components', $commands);
    }

    public function test_its_config_defaults_are_merged_under_guardian_livewire(): void
    {
        $this->assertArrayHasKey('cache_path', config('guardian.livewire'));
    }

    #[Group('livewire')]
    public function test_the_middleware_livewire_must_keep_on_updates_is_registered(): void
    {
        $persistent = Livewire::getPersistentMiddleware();

        $this->assertContains(Authenticate::class, $persistent);
        $this->assertContains(DispatchServingGuardianEvent::class, $persistent);
        $this->assertContains(SetUpFortress::class, $persistent);
    }
}
