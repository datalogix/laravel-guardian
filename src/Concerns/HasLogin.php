<?php

namespace Datalogix\Guardian\Concerns;

use Closure;
use Datalogix\Guardian\Enums\Layout;
use Datalogix\Guardian\Features\LoginFeature;

trait HasLogin
{
    protected ?LoginFeature $loginFeature = null;

    public function getLoginFeature(): LoginFeature
    {
        return $this->loginFeature ??= new LoginFeature($this);
    }

    public function login(
        string|Closure|array|false|null $routeAction = null,
        ?string $routeSlug = null,
        ?string $routeName = null,
        string|Closure|null $response = null,
        int|false|null $maxAttempts = null,
        Layout|string|null $layout = null,
    ): static {
        $this->getLoginFeature()->configure(
            $routeAction,
            $routeSlug,
            $routeName,
            $response,
            $maxAttempts,
            $layout,
        );

        return $this;
    }

    public function loginRoutes(): static
    {
        $this->getLoginFeature()->registerRoutes();

        return $this;
    }
}
