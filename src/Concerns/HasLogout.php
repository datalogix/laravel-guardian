<?php

namespace Datalogix\Guardian\Concerns;

use Closure;
use Datalogix\Guardian\Actions\Logout;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

trait HasLogout
{
    protected string|Closure|array|null $logoutRouteAction = null;

    protected ?string $logoutRouteSlug = null;

    protected ?string $logoutRouteName = null;

    public function logout(
        string|Closure|array|null $logoutRouteAction = null,
        ?string $logoutRouteSlug = null,
        ?string $logoutRouteName = null,
    ): static {
        $this->logoutRouteAction = $logoutRouteAction ?? Logout::class;
        $this->logoutRouteSlug = $logoutRouteSlug ?? 'logout';
        $this->logoutRouteName = $logoutRouteName ?? 'auth.logout';

        return $this;
    }

    public function getLogoutUrl(array $parameters = []): ?string
    {
        return $this->hasLogout()
            ? $this->route($this->getLogoutRouteName(), $parameters)
            : null;
    }

    public function getLogoutRouteAction(): string|Closure|array|null
    {
        return $this->logoutRouteAction;
    }

    public function getLogoutRouteSlug(): string
    {
        return Str::start($this->logoutRouteSlug, '/');
    }

    public function getLogoutRouteName(): ?string
    {
        return $this->logoutRouteName;
    }

    public function hasLogout(): bool
    {
        return filled($this->getLogoutRouteAction());
    }

    public function logoutRoutes(): static
    {
        if ($this->hasLogout()) {
            Route::any($this->getLogoutRouteSlug(), $this->getLogoutRouteAction())
                ->middleware($this->getAuthMiddleware())
                ->name($this->getLogoutRouteName());
        }

        return $this;
    }
}
