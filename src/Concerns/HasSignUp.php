<?php

namespace Datalogix\Guardian\Concerns;

use Closure;
use Datalogix\Guardian\Enums\Layout;
use Datalogix\Guardian\Features\SignUpFeature;

trait HasSignUp
{
    protected ?SignUpFeature $signUpFeature = null;

    public function getSignUpFeature(): SignUpFeature
    {
        return $this->signUpFeature ??= new SignUpFeature($this);
    }

    public function signUp(
        string|Closure|array|false|null $routeAction = null,
        ?string $routeSlug = null,
        ?string $routeName = null,
        string|Closure|null $response = null,
        int|false|null $maxAttempts = null,
        Layout|string|null $layout = null,
    ): static {
        $this->getSignUpFeature()->configure(
            $routeAction,
            $routeSlug,
            $routeName,
            $response,
            $maxAttempts,
            $layout,
        );

        return $this;
    }

    public function signUpRoutes(): static
    {
        $this->getSignUpFeature()->registerRoutes();

        return $this;
    }
}
