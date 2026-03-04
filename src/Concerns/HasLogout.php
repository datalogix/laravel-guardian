<?php

namespace Datalogix\Guardian\Concerns;

use Closure;
use Datalogix\Guardian\Features\LogoutFeature;

trait HasLogout
{
    protected ?LogoutFeature $logoutFeature = null;

    public function getLogoutFeature(): LogoutFeature
    {
        return $this->logoutFeature ??= new LogoutFeature($this);
    }

    public function logout(
        string|Closure|array|false|null $routeAction = null,
        ?string $routeSlug = null,
        ?string $routeName = null,
        string|Closure|null $response = null,
        int|false|null $maxAttempts = null,
    ): static {
        $this->getLogoutFeature()->configure(
            $routeAction,
            $routeSlug,
            $routeName,
            $response,
            $maxAttempts,
        );

        return $this;
    }

    public function logoutRoutes(): static
    {
        $this->getLogoutFeature()->registerRoutes();

        return $this;
    }
}
