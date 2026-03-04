<?php

namespace Datalogix\Guardian;

use Datalogix\Guardian\Enums\Framework;
use Datalogix\Guardian\Framework\FrameworkResolver;
use Datalogix\Guardian\Framework\InertiaComponentFactory;
use Datalogix\Guardian\Framework\LivewireComponentFactory;
use Datalogix\Guardian\Http\Middleware\Authenticate;
use Datalogix\Guardian\Http\Middleware\DispatchServingGuardianEvent;
use Datalogix\Guardian\Http\Middleware\SetUpFortress;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class GuardianServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/guardian.php', 'guardian');

        $this->app->scoped('guardian', fn () => new GuardianManager);
        $this->app->alias('guardian', GuardianManager::class);
        $this->app->singleton(FortressRegistry::class, fn () => new FortressRegistry);

        $this->app->singleton(FrameworkResolver::class, function () {
            $resolver = new FrameworkResolver;
            $resolver->register(Framework::Inertia, new InertiaComponentFactory);
            $resolver->register(Framework::Livewire, new LivewireComponentFactory);

            return $resolver;
        });

        app(Router::class)->aliasMiddleware('guardian', SetUpFortress::class);
    }

    public function boot(): void
    {
        app()->booted(function () {
            app(FortressRegistry::class)->validate();

            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        });

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'guardian');

        if (class_exists(Livewire::class)) {
            Livewire::addPersistentMiddleware([
                Authenticate::class,
                DispatchServingGuardianEvent::class,
                SetUpFortress::class,
            ]);
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                Commands\CacheComponentsCommand::class,
                Commands\ClearCachedComponentsCommand::class,
            ]);
        }

        Guardian::serving(fn () => Guardian::setServingStatus());
    }
}
