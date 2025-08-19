<?php

namespace Datalogix\Guardian\Concerns;

use Closure;
use Datalogix\Guardian\Enums\Framework;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

trait HasPasswordConfirmation
{
    protected string|Closure|null $passwordConfirmationMiddlewareName = null;
    protected string|Closure|array|null $passwordConfirmationRouteAction = null;

    protected ?string $passwordConfirmationRouteSlug = null;

    protected ?string $passwordConfirmationRouteName = null;

    public function passwordConfirmation(
        string|Closure|array|null $passwordConfirmationRouteAction = null,
        ?string $passwordConfirmationRouteSlug = null,
        ?string $passwordConfirmationRouteName = null,
        string|Closure|null $passwordConfirmationMiddlewareName = null,
    ): static {
        $this->passwordConfirmationRouteAction = $passwordConfirmationRouteAction ?? match ($this->getFramework()) {
            Framework::Livewire => \Datalogix\Guardian\Http\Livewire\ConfirmPassword::class,
        };
        $this->passwordConfirmationRouteSlug = $passwordConfirmationRouteSlug ?? 'confirm-password';
        $this->passwordConfirmationRouteName = $passwordConfirmationRouteName ?? 'auth.password.confirm';
        $this->passwordConfirmationMiddlewareName = $passwordConfirmationMiddlewareName ?? 'password.confirm';

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
