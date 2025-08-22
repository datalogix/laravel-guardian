<?php

namespace Datalogix\Guardian\Concerns;

use Closure;
use Datalogix\Guardian\Actions\Logout;
use Datalogix\Guardian\Http\Responses\LogoutResponse;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

trait HasLogout
{
    protected string|Closure|array|null $logoutRouteAction = null;

    protected ?string $logoutRouteSlug = null;

    protected ?string $logoutRouteName = null;

    protected string|Closure|null $logoutResponse = null;

    public function logout(
        string|Closure|array|null $routeAction = null,
        ?string $routeSlug = null,
        ?string $routeName = null,
        string|Closure|null $response = null,
    ): static {
        $this->logoutRouteAction = $routeAction ?? Logout::class;
        $this->logoutRouteSlug = $routeSlug ?? 'logout';
        $this->logoutRouteName = $routeName ?? 'auth.logout';
        $this->logoutResponse = $response ?? LogoutResponse::class;

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

    public function getLogoutResponse()
    {
        return value($this->logoutResponse);
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
