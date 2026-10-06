<?php

namespace Datalogix\Guardian;

use Datalogix\Guardian\Framework\FrameworkResolver;
use Datalogix\Guardian\Framework\Inertia\InertiaServiceProvider;
use Datalogix\Guardian\Framework\Livewire\LivewireServiceProvider;
use Datalogix\Guardian\Support\Auth\GuestRedirect;
use Datalogix\Guardian\Support\TwoFactor\TwoFactorUser;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Log\Context\Repository as Context;
use Illuminate\Support\Facades\Context as ContextFacade;
use Illuminate\Support\ServiceProvider;

class GuardianServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/guardian.php', 'guardian');

        $this->app->scoped('guardian', fn () => new GuardianManager);
        $this->app->alias('guardian', GuardianManager::class);
        $this->app->singleton(FortressRegistry::class, fn () => new FortressRegistry);
        $this->app->scoped(TwoFactorUser::class);

        $this->app->singleton(FrameworkResolver::class);

        $this->app->register(LivewireServiceProvider::class);
        $this->app->register(InertiaServiceProvider::class);
    }

    public function boot(): void
    {
        $this->loadJsonTranslationsFrom(__DIR__.'/../resources/lang');

        $this->publishes([
            __DIR__.'/../config/guardian.php' => config_path('guardian.php'),
        ], 'guardian-config');

        $this->publishes([
            __DIR__.'/../resources/lang' => $this->app->langPath('vendor/guardian'),
        ], 'guardian-lang');

        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'guardian-migrations');

        app()->booted(function () {
            app(FortressRegistry::class)->validate();

            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

            $fortresses = app(FortressRegistry::class)->all();

            $this->loadMigrationsConditionally($fortresses);

            $this->scheduleTrustedDevicePruningConditionally($fortresses);

            $this->registerNotificationUrlsConditionally($fortresses);

            GuestRedirect::wrap();
        });

        // Laravel sets its destination of guests again whenever the HTTP kernel is made.
        $this->app->afterResolving(HttpKernel::class, fn () => GuestRedirect::wrap());

        $this->carryCurrentFortressToQueuedJobs();

        if ($this->app->runningInConsole()) {
            $this->commands([
                Commands\PruneTrustedDevicesCommand::class,
            ]);
        }

        Guardian::serving(fn () => Guardian::setServingStatus());
    }

    protected function loadMigrationsConditionally(array $fortresses): void
    {
        if (! (config('guardian.migrations') ?? true)) {
            return;
        }

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

    /**
     * Registered globally: queued e-mails render their links in a queue worker.
     */
    protected function registerNotificationUrlsConditionally(array $fortresses): void
    {
        $fortresses = collect($fortresses);

        if (ResetPassword::$createUrlCallback === null
            && $fortresses->some(fn ($fortress) => $fortress->getResetPasswordFeature()->hasFeature())) {
            ResetPassword::createUrlUsing(fn (mixed $notifiable, string $token) => Guardian::getResetPasswordUrl($token, $notifiable));
        }

        if (VerifyEmail::$createUrlCallback === null
            && $fortresses->some(fn ($fortress) => $fortress->getEmailVerificationVerifyFeature()->hasFeature())) {
            VerifyEmail::createUrlUsing(fn (mixed $notifiable) => Guardian::getVerifyEmailUrl($notifiable));
        }
    }

    protected function carryCurrentFortressToQueuedJobs(): void
    {
        ContextFacade::dehydrating(function (Context $context) {
            if ($fortress = Guardian::getCurrentFortress()) {
                $context->addHidden('guardian.fortress', $fortress->getId());
            }
        });

        ContextFacade::hydrated(function (Context $context) {
            if ($id = $context->getHidden('guardian.fortress')) {
                Guardian::setCurrentFortress(Guardian::getFortress($id));
            }
        });
    }

    protected function scheduleTrustedDevicePruningConditionally(array $fortresses): void
    {
        if (! $this->app->bound(Schedule::class) || ! (config('guardian.prune_trusted_devices.enabled') ?? true)) {
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
