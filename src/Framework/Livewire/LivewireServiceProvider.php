<?php

namespace Datalogix\Guardian\Framework\Livewire;

use Datalogix\Guardian\Framework\FrameworkResolver;
use Datalogix\Guardian\Framework\Livewire\Commands\CacheComponentsCommand;
use Datalogix\Guardian\Framework\Livewire\Commands\ClearCachedComponentsCommand;
use Datalogix\Guardian\Http\Middleware\Authenticate;
use Datalogix\Guardian\Http\Middleware\DispatchServingGuardianEvent;
use Datalogix\Guardian\Http\Middleware\SetUpFortress;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

/**
 * Wires the Livewire front-end into Guardian: its adapter, views, commands,
 * config and the middleware Livewire must keep on its update requests.
 */
class LivewireServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/config/livewire.php', 'guardian.livewire');

        $this->app->singleton(LivewireAdapter::class);

        $this->callAfterResolving(
            FrameworkResolver::class,
            fn (FrameworkResolver $resolver) => $resolver->register($this->app->make(LivewireAdapter::class)),
        );
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/resources/views', 'guardian');

        $this->publishes([
            __DIR__.'/resources/views' => $this->app->resourcePath('views/vendor/guardian'),
        ], 'guardian-views');

        if ($this->app->runningInConsole()) {
            $this->commands([
                CacheComponentsCommand::class,
                ClearCachedComponentsCommand::class,
            ]);
        }

        if ($this->app->make(LivewireAdapter::class)->isInstalled()) {
            Livewire::addPersistentMiddleware([
                Authenticate::class,
                DispatchServingGuardianEvent::class,
                SetUpFortress::class,
            ]);
        }
    }
}
