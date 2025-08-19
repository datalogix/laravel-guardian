<?php

namespace Datalogix\Guardian\Concerns;

use Closure;
use Datalogix\Guardian\Enums\Framework;
use Datalogix\Guardian\Http\Middleware\RedirectIfAuthenticated;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

trait HasSignUp
{
    protected string|Closure|array|null $signUpRouteAction = null;

    protected ?string $signUpRouteSlug = null;

    protected ?string $signUpRouteName = null;

    public function signUp(
        string|Closure|array|null $signUpRouteAction = null,
        ?string $signUpRouteSlug = null,
        ?string $signUpRouteName = null,
    ): static {
        $this->signUpRouteAction = $signUpRouteAction ?? match ($this->getFramework()) {
            Framework::Livewire => \Datalogix\Guardian\Http\Livewire\SignUp::class,
        };
        $this->signUpRouteSlug = $signUpRouteSlug ?? 'sign-up';
        $this->signUpRouteName = $signUpRouteName ?? 'auth.sign-up';

        return $this;
    }

    public function getSignUpUrl(array $parameters = []): ?string
    {
        return $this->hasSignUp()
            ? $this->route($this->getSignUpRouteName(), $parameters)
            : null;
    }

    public function getSignUpRouteAction(): string|Closure|array|null
    {
        return $this->signUpRouteAction;
    }

    public function getSignUpRouteSlug(): string
    {
        return Str::start($this->signUpRouteSlug, '/');
    }

    public function getSignUpRouteName(): ?string
    {
        return $this->signUpRouteName;
    }

    public function hasSignUp(): bool
    {
        return filled($this->getSignUpRouteAction());
    }

    public function signUpRoutes(): static
    {
        if ($this->hasSignUp()) {
            Route::get($this->getSignUpRouteSlug(), $this->getSignUpRouteAction())
                ->middleware(RedirectIfAuthenticated::class)
                ->name($this->getSignUpRouteName());
        }

        return $this;
    }
}
