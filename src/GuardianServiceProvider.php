<?php

namespace Datalogix\Guardian;

use Datalogix\Guardian\Enums\Framework;
use Datalogix\Guardian\Framework\FrameworkResolver;
use Datalogix\Guardian\Framework\InertiaComponentFactory;
use Datalogix\Guardian\Framework\LivewireComponentFactory;
use Datalogix\Guardian\Http\Middleware\Authenticate;
use Datalogix\Guardian\Http\Middleware\DispatchServingGuardianEvent;
use Datalogix\Guardian\Http\Middleware\SetUpFortress;
use Datalogix\Guardian\Support\TwoFactor\TwoFactorUser;
use Illuminate\Console\Scheduling\Schedule;
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
        $this->app->scoped(TwoFactorUser::class);

        $this->app->singleton(FrameworkResolver::class, function () {
            $resolver = new FrameworkResolver;
            $resolver->register(Framework::Inertia, new InertiaComponentFactory);
            $resolver->register(Framework::Livewire, new LivewireComponentFactory);

            return $resolver;
        });
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'guardian');
        $this->loadJsonTranslationsFrom(__DIR__.'/../resources/lang');

        $this->publishes([
            __DIR__.'/../config/guardian.php' => config_path('guardian.php'),
        ], 'guardian-config');

        $this->publishes([
            __DIR__.'/../resources/views' => $this->app->resourcePath('views/vendor/guardian'),
        ], 'guardian-views');

        $this->publishes([
            __DIR__.'/../resources/lang' => $this->app->langPath('vendor/guardian'),
        ], 'guardian-lang');

        app()->booted(function () {
            app(FortressRegistry::class)->validate();

            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

            $fortresses = app(FortressRegistry::class)->all();

            $this->loadMigrationsConditionally($fortresses);

            $this->scheduleTrustedDevicePruningConditionally($fortresses);
        });

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
                Commands\PruneTrustedDevicesCommand::class,
            ]);
        }

        Guardian::serving(fn () => Guardian::setServingStatus());
    }

    protected function loadMigrationsConditionally(array $fortresses): void
    {
        $hasTwoFactor = collect($fortresses)->some(
            fn ($fortress) => $fortress->hasAnyTwoFactorFeature()
        );

        $hasTrustedDevices = collect($fortresses)->some(
            fn ($fortress) => $fortress->hasAnyTwoFactorFeature() && $fortress->shouldTwoFactorRememberOnDevice()
        );

        $hasOAuth = collect($fortresses)->some(
            fn ($fortress) => $fortress->getOAuthFeature()->hasFeature()
        );

        $migrations = [];

        if ($hasTwoFactor) {
            $migrations[] = __DIR__.'/../database/migrations/2026_01_01_000000_add_two_factor_columns_to_users_table.php';
        }

        if ($hasTrustedDevices) {
            $migrations[] = __DIR__.'/../database/migrations/2026_01_01_000001_create_two_factor_trusted_devices_table.php';
        }

        if ($hasOAuth) {
            $migrations[] = __DIR__.'/../database/migrations/2026_01_01_000002_create_oauth_identities_table.php';
        }

        if ($migrations !== []) {
            $this->loadMigrationsFrom($migrations);
        }
    }

    protected function scheduleTrustedDevicePruningConditionally(array $fortresses): void
    {
        if (! $this->app->bound(Schedule::class)) {
            return;
        }

        $hasTrustedDevices = collect($fortresses)->some(
            fn ($fortress) => $fortress->hasAnyTwoFactorFeature() && $fortress->shouldTwoFactorRememberOnDevice()
        );

        if (! $hasTrustedDevices) {
            return;
        }

        $this->app->make(Schedule::class)
            ->command('guardian:prune-trusted-devices')
            ->daily();
    }
}
