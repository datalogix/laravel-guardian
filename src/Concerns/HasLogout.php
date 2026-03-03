<?php

namespace Datalogix\Guardian\Concerns;

use Closure;
use Datalogix\Guardian\Http\Controllers\LogoutController;
use Datalogix\Guardian\Http\Responses\LogoutResponse;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

trait HasLogout
{
    protected string|Closure|array|false|null $logoutRouteAction = null;

    protected ?string $logoutRouteSlug = null;

    protected ?string $logoutRouteName = null;

    protected string|Closure|null $logoutResponse = null;

    public function logout(
        string|Closure|array|false|null $routeAction = null,
        ?string $routeSlug = null,
        ?string $routeName = null,
        string|Closure|null $response = null,
    ): static {
        $this->logoutRouteAction = $routeAction ?? LogoutController::class;
        $this->logoutRouteSlug = $routeSlug ?? 'logout';
        $this->logoutRouteName = $routeName ?? 'auth.logout';
        $this->logoutResponse = $response ?? LogoutResponse::class;

        return $this;
    }

    public function getLogoutRouteAction(): string|Closure|array|false|null
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

    public function getLogoutUrl(array $parameters = []): ?string
    {
        return $this->hasLogout()
            ? $this->route($this->getLogoutRouteName(), $parameters)
            : null;
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
