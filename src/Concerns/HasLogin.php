<?php

namespace Datalogix\Guardian\Concerns;

use Closure;
use Datalogix\Guardian\Enums\Framework;
use Datalogix\Guardian\Enums\Layout;
use Datalogix\Guardian\Http\Middleware\RedirectIfAuthenticated;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

trait HasLogin
{
    protected string|Closure|array|null $loginRouteAction = null;

    protected ?string $loginRouteSlug = null;

    protected ?string $loginRouteName = null;

    protected int|false|null $loginMaxAttempts = null;

    public function login(
        string|Closure|array|null $routeAction = null,
        ?string $routeSlug = null,
        ?string $routeName = null,
        int|false|null $maxAttempts = null,
        null|string|Layout $layout = null,
    ): static {
        $this->loginRouteAction = $routeAction ?? match ($this->getFramework()) {
            Framework::Livewire => \Datalogix\Guardian\Http\Livewire\Login::class,
        };
        $this->loginRouteSlug = $routeSlug ?? 'login';
        $this->loginRouteName = $routeName ?? 'auth.login';
        $this->loginMaxAttempts = $maxAttempts;
        $this->layoutForPage('login', $layout);

        return $this;
    }

    public function getLoginUrl(array $parameters = []): ?string
    {
        return $this->hasLogin()
            ? $this->route($this->getLoginRouteName(), $parameters)
            : null;
    }

    public function getLoginRouteAction(): string|Closure|array|null
    {
        return $this->loginRouteAction;
    }

    public function getLoginRouteSlug(): string
    {
        return Str::start($this->loginRouteSlug, '/');
    }

    public function getLoginRouteName(): ?string
    {
        return $this->loginRouteName;
    }

    public function getLoginMaxAttempts(): int|false|null
    {
        return $this->loginMaxAttempts;
    }

    public function hasLogin(): bool
    {
        return filled($this->getLoginRouteAction());
    }

    public function loginRoutes(): static
    {
        if ($this->hasLogin()) {
            Route::get($this->getLoginRouteSlug(), $this->getLoginRouteAction())
                ->middleware(RedirectIfAuthenticated::class)
                ->name($this->getLoginRouteName());
        }

        return $this;
    }
}
