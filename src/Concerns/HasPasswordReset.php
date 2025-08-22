<?php

namespace Datalogix\Guardian\Concerns;

use Closure;
use Datalogix\Guardian\Enums\Framework;
use Datalogix\Guardian\Enums\Layout;
use Datalogix\Guardian\Http\Middleware\RedirectIfAuthenticated;
use Datalogix\Guardian\Http\Responses\ResetPasswordResponse;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

trait HasPasswordReset
{
    protected string|Closure|array|null $forgotPasswordRouteAction = null;

    protected ?string $forgotPasswordRouteSlug = null;

    protected ?string $forgotPasswordRouteName = null;

    protected string|Closure|array|null $resetPasswordRouteAction = null;

    protected ?string $resetPasswordRouteSlug = null;

    protected ?string $resetPasswordRouteName = null;

    protected string|Closure|null $resetPasswordResponse = null;

    protected ?string $passwordBroker = null;

    public function passwordReset(
        string|Closure|array|null $forgotPasswordRouteAction = null,
        ?string $forgotPasswordRouteSlug = null,
        ?string $forgotPasswordRouteName = null,
        null|string|Layout $forgotPasswordLayout = null,
        string|Closure|array|null $resetPasswordRouteAction = null,
        ?string $resetPasswordRouteSlug = null,
        ?string $resetPasswordRouteName = null,
        string|Closure|null $resetPasswordResponse = null,
        null|string|Layout $resetPasswordLayout = null,
    ): static {
        $this->forgotPasswordRouteAction = $forgotPasswordRouteAction ?? match ($this->getFramework()) {
            Framework::Livewire => \Datalogix\Guardian\Http\Livewire\ForgotPassword::class,
        };
        $this->forgotPasswordRouteSlug = $forgotPasswordRouteSlug ?? 'forgot-password';
        $this->forgotPasswordRouteName = $forgotPasswordRouteName ?? 'auth.password.request';
        $this->layoutForPage('forgot-password', $forgotPasswordLayout);

        $this->resetPasswordRouteAction = $resetPasswordRouteAction ?? match ($this->getFramework()) {
            Framework::Livewire => \Datalogix\Guardian\Http\Livewire\ResetPassword::class,
        };
        $this->resetPasswordRouteSlug = $resetPasswordRouteSlug ?? 'reset-password';
        $this->resetPasswordRouteName = $resetPasswordRouteName ?? 'auth.password.reset';
        $this->resetPasswordResponse = $resetPasswordResponse ?? ResetPasswordResponse::class;
        $this->layoutForPage('reset-password', $resetPasswordLayout);

        return $this;
    }

    public function passwordBroker(?string $passwordBroker = null): static
    {
        $this->passwordBroker = $passwordBroker;

        return $this;
    }

    public function getForgotPasswordUrl(array $parameters = []): ?string
    {
        return $this->hasPasswordReset()
            ? $this->route('auth.password.request', $parameters)
            : null;
    }

    public function getResetPasswordUrl(string $token, CanResetPassword|Model|Authenticatable $user, array $parameters = []): string
    {
        return URL::signedRoute(
            $this->generateRouteName('auth.password.reset'),
            [
                'email' => $user->getEmailForPasswordReset(),
                'token' => $token,
                ...$parameters,
            ],
        );
    }

    public function getForgotPasswordRouteAction(): string|Closure|array|null
    {
        return $this->forgotPasswordRouteAction;
    }

    public function getForgotPasswordRouteSlug(): string
    {
        return Str::start($this->forgotPasswordRouteSlug, '/');
    }

    public function getForgotPasswordRouteName(): ?string
    {
        return $this->forgotPasswordRouteName;
    }

    public function getResetPasswordRouteAction(): string|Closure|array|null
    {
        return $this->resetPasswordRouteAction;
    }

    public function getResetPasswordRouteSlug(string $suffix = ''): string
    {
        return Str::start($this->resetPasswordRouteSlug, '/').$suffix;
    }

    public function getResetPasswordRouteName(): ?string
    {
        return $this->resetPasswordRouteName;
    }

    public function getResetPasswordResponse()
    {
        return value($this->resetPasswordResponse);
    }

    public function getPasswordBroker(): ?string
    {
        return $this->passwordBroker;
    }

    public function hasPasswordReset(): bool
    {
        return filled($this->getForgotPasswordRouteAction()) && filled($this->getResetPasswordRouteAction());
    }

    public function passwordResetRoutes(): static
    {
        if ($this->hasPasswordReset()) {
            Route::middleware(RedirectIfAuthenticated::class)->group(function () {
                Route::get($this->getForgotPasswordRouteSlug(), $this->getForgotPasswordRouteAction())
                    ->name($this->getForgotPasswordRouteName());

                Route::get($this->getResetPasswordRouteSlug('/{token?}'), $this->getResetPasswordRouteAction())
                    ->middleware('signed')
                    ->name($this->getResetPasswordRouteName());
            });
        }

        return $this;
    }
}
