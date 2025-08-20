<?php

namespace Datalogix\Guardian\Concerns;

use Closure;
use Datalogix\Guardian\Enums\Framework;
use Datalogix\Guardian\Enums\Layout;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

trait HasPasswordConfirmation
{
    protected string|Closure|null $passwordConfirmationMiddlewareName = null;

    protected string|Closure|array|null $passwordConfirmationRouteAction = null;

    protected ?string $passwordConfirmationRouteSlug = null;

    protected ?string $passwordConfirmationRouteName = null;

    public function passwordConfirmation(
        string|Closure|array|null $routeAction = null,
        ?string $routeSlug = null,
        ?string $routeName = null,
        string|Closure|null $middlewareName = null,
        null|string|Layout $layout = null,
    ): static {
        $this->passwordConfirmationRouteAction = $routeAction ?? match ($this->getFramework()) {
            Framework::Livewire => \Datalogix\Guardian\Http\Livewire\ConfirmPassword::class,
        };
        $this->passwordConfirmationRouteSlug = $routeSlug ?? 'confirm-password';
        $this->passwordConfirmationRouteName = $routeName ?? 'auth.password.confirm';
        $this->passwordConfirmationMiddlewareName = $middlewareName ?? 'password.confirm';
        $this->layoutForPage('confirm-password', $layout);

        return $this;
    }

    public function getPasswordConfirmationUrl(array $parameters = []): ?string
    {
        return $this->hasPasswordConfirmation()
            ? $this->route($this->getPasswordConfirmationRouteName(), $parameters)
            : null;
    }

    public function getPasswordConfirmationRouteAction(): string|Closure|array|null
    {
        return $this->passwordConfirmationRouteAction;
    }

    public function getPasswordConfirmationRouteSlug(): string
    {
        return Str::start($this->passwordConfirmationRouteSlug, '/');
    }

    public function getPasswordConfirmationRouteName(): ?string
    {
        return $this->passwordConfirmationRouteName;
    }

    public function hasPasswordConfirmation(): bool
    {
        return filled($this->getPasswordConfirmationRouteAction());
    }

    public function passwordConfirmationRoutes(): static
    {
        if ($this->hasPasswordConfirmation()) {
            Route::get($this->getPasswordConfirmationRouteSlug(), $this->getPasswordConfirmationRouteAction())
                ->name($this->getPasswordConfirmationRouteName());
        }

        return $this;
    }

    public function getPasswordConfirmationMiddlewareName(): ?string
    {
        return value($this->passwordConfirmationMiddlewareName);
    }

    public function getPasswordConfirmationMiddleware(): ?string
    {
        return $this->hasPasswordConfirmation()
            ? "{$this->getPasswordConfirmationMiddlewareName()}:{$this->generateRouteName($this->getPasswordConfirmationRouteName())}"
            : null;
    }

    public function passwordConfirm()
    {
        return $this->getPasswordConfirmationMiddleware();
    }
}
