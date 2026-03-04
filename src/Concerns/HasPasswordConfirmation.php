<?php

namespace Datalogix\Guardian\Concerns;

use Closure;
use Datalogix\Guardian\Enums\Layout;
use Datalogix\Guardian\Features\PasswordConfirmationFeature;

trait HasPasswordConfirmation
{
    protected ?PasswordConfirmationFeature $passwordConfirmationFeature = null;

    protected ?string $passwordConfirmationMiddlewareName = null;

    public function getPasswordConfirmationFeature(): PasswordConfirmationFeature
    {
        return $this->passwordConfirmationFeature ??= new PasswordConfirmationFeature($this);
    }

    public function passwordConfirmation(
        string|Closure|array|false|null $routeAction = null,
        ?string $routeSlug = null,
        ?string $routeName = null,
        string|Closure|null $response = null,
        string|Closure|null $middlewareName = null,
        int|false|null $maxAttempts = null,
        Layout|string|null $layout = null,
    ): static {
        $this->getPasswordConfirmationFeature()->configure(
            $routeAction,
            $routeSlug,
            $routeName,
            $response,
            $maxAttempts,
            $layout,
        );

        $this->passwordConfirmationMiddlewareName = $middlewareName ?? 'password.confirm';

        return $this;
    }

    public function getPasswordConfirmationMiddlewareName(): ?string
    {
        return $this->passwordConfirmationMiddlewareName;
    }

    public function getPasswordConfirmationMiddleware(): ?string
    {
        return $this->hasFeature()
            ? "{$this->getPasswordConfirmationMiddlewareName()}:{$this->generateRouteName($this->getPasswordConfirmationFeature()->getRouteName())}"
            : null;
    }

    public function passwordConfirm(): ?string
    {
        return $this->getPasswordConfirmationMiddleware();
    }

    public function passwordConfirmationRoutes(): static
    {
        $this->getPasswordConfirmationFeature()->registerRoutes();

        return $this;
    }
}
