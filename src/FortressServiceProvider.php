<?php

namespace Datalogix\Fortress;

use Datalogix\Fortress\Facades\Fortress;
use Datalogix\Fortress\Http\Middleware\Authenticate;
use Datalogix\Fortress\Http\Middleware\DispatchServingFortressEvent;
use Datalogix\Fortress\Http\Middleware\SetUpFortress;
use Datalogix\Fortress\Http\Responses\Auth\Contracts\EmailVerificationResponse as EmailVerificationResponseContract;
use Datalogix\Fortress\Http\Responses\Auth\Contracts\LoginResponse as LoginResponseContract;
use Datalogix\Fortress\Http\Responses\Auth\Contracts\LogoutResponse as LogoutResponseContract;
use Datalogix\Fortress\Http\Responses\Auth\Contracts\PasswordResetResponse as PasswordResetResponseContract;
use Datalogix\Fortress\Http\Responses\Auth\Contracts\RegistrationResponse as RegistrationResponseContract;
use Datalogix\Fortress\Http\Responses\Auth\EmailVerificationResponse;
use Datalogix\Fortress\Http\Responses\Auth\LoginResponse;
use Datalogix\Fortress\Http\Responses\Auth\LogoutResponse;
use Datalogix\Fortress\Http\Responses\Auth\PasswordResetResponse;
use Datalogix\Fortress\Http\Responses\Auth\RegistrationResponse;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class FortressServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/fortress.php', 'laravel-fortress');

        $this->app->scoped('fortress', fn () => new FortressManager);
        $this->app->alias('fortress', FortressManager::class);
        $this->app->singleton(FortressRegistry::class, fn () => new FortressRegistry);

        $this->app->bind(EmailVerificationResponseContract::class, EmailVerificationResponse::class);
        $this->app->bind(LoginResponseContract::class, LoginResponse::class);
        $this->app->bind(LogoutResponseContract::class, LogoutResponse::class);
        $this->app->bind(PasswordResetResponseContract::class, PasswordResetResponse::class);
        $this->app->bind(RegistrationResponseContract::class, RegistrationResponse::class);

        app(Router::class)->aliasMiddleware('fortress', SetUpFortress::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'laravel-fortress');

        Livewire::addPersistentMiddleware([
            Authenticate::class,
            DispatchServingFortressEvent::class,
            SetUpFortress::class,
        ]);

        if ($this->app->runningInConsole()) {
            $this->commands([
                Commands\CacheComponentsCommand::class,
                Commands\ClearCachedComponentsCommand::class,
            ]);
        }

        Fortress::serving(fn () => Fortress::setServingStatus());
    }
}
