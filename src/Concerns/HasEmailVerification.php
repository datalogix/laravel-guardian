<?php

namespace Datalogix\Guardian\Concerns;

use Closure;
use Datalogix\Guardian\Actions\EmailVerification;
use Datalogix\Guardian\Enums\Framework;
use Datalogix\Guardian\Enums\Layout;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

trait HasEmailVerification
{
    protected string|Closure|null $emailVerifiedMiddlewareName = null;

    protected string|Closure|array|null $emailVerificationPromptRouteAction = null;

    protected ?string $emailVerificationPromptRouteSlug = null;

    protected ?string $emailVerificationPromptRouteName = null;

    protected string|Closure|array|null $emailVerificationRouteAction = null;

    protected ?string $emailVerificationRouteSlug = null;

    protected ?string $emailVerificationRouteName = null;

    protected ?bool $isEmailVerificationRequired = null;

    public function emailVerification(
        string|Closure|array|null $promptRouteAction = null,
        ?string $promptRouteSlug = null,
        ?string $promptRouteName = null,
        null|string|Layout $layout = null,
        string|Closure|array|null $routeAction = null,
        ?string $routeSlug = null,
        ?string $routeName = null,
        string|Closure|null $middlewareName = null,
        ?bool $isRequired = null,
    ): static {
        $this->emailVerificationPromptRouteAction = $promptRouteAction ?? match ($this->getFramework()) {
            Framework::Livewire => \Datalogix\Guardian\Http\Livewire\EmailVerificationPrompt::class,
        };
        $this->emailVerificationPromptRouteSlug = $promptRouteSlug ?? 'email-verification/prompt';
        $this->emailVerificationPromptRouteName = $promptRouteName ?? 'auth.email-verification.prompt';
        $this->layoutForPage('email-verification-prompt', $layout);

        $this->emailVerificationRouteAction = $routeAction ?? EmailVerification::class;
        $this->emailVerificationRouteSlug = $routeSlug ?? 'email-verification/verify';
        $this->emailVerificationRouteName = $routeName ?? 'auth.email-verification.verify';

        $this->emailVerifiedMiddlewareName = $middlewareName ?? 'verified';
        $this->isEmailVerificationRequired = $isRequired ?? true;

        return $this;
    }

    public function getEmailVerificationPromptUrl(array $parameters = []): ?string
    {
        if (! $this->hasEmailVerification()) {
            return null;
        }

        return route($this->getEmailVerificationPromptRouteName(), $parameters);
    }

    public function getVerifyEmailUrl(MustVerifyEmail|Model|Authenticatable $user, array $parameters = []): string
    {
        return URL::temporarySignedRoute(
            $this->generateRouteName($this->getEmailVerificationRouteName()),
            now()->addMinutes(config('auth.verification.expire', 60)),
            [
                'id' => $user->getKey(),
                'hash' => sha1($user->getEmailForVerification()),
                ...$parameters,
            ],
        );
    }

    public function getEmailVerificationPromptRouteAction(): string|Closure|array|null
    {
        return $this->emailVerificationPromptRouteAction;
    }

    public function getEmailVerificationPromptRouteSlug(): string
    {
        return Str::start($this->emailVerificationPromptRouteSlug, '/');
    }

    public function getEmailVerificationPromptRouteName(): ?string
    {
        return $this->emailVerificationPromptRouteName;
    }

    public function getEmailVerificationRouteAction(): string|Closure|array|null
    {
        return $this->emailVerificationRouteAction;
    }

    public function getEmailVerificationRouteSlug(string $suffix): string
    {
        return Str::start($this->emailVerificationRouteSlug, '/').$suffix;
    }

    public function getEmailVerificationRouteName(): ?string
    {
        return $this->emailVerificationRouteName;
    }

    public function getEmailVerifiedMiddlewareName(): ?string
    {
        return value($this->emailVerifiedMiddlewareName);
    }

    public function getEmailVerifiedMiddleware(): ?string
    {
        return $this->isEmailVerificationRequired()
            ? "{$this->getEmailVerifiedMiddlewareName()}:{$this->generateRouteName($this->getEmailVerificationPromptRouteName())}"
            : null;
    }

    public function isEmailVerificationRequired(): ?bool
    {
        return $this->isEmailVerificationRequired;
    }

    public function hasEmailVerification(): bool
    {
        return filled($this->getEmailVerificationPromptRouteAction()) && filled($this->getEmailVerificationRouteAction());
    }

    public function emailVerificationRoutes(): static
    {
        if ($this->hasEmailVerification()) {
            Route::middleware($this->getAuthMiddleware())->group(function () {
                Route::get($this->getEmailVerificationPromptRouteSlug(), $this->getEmailVerificationPromptRouteAction())
                    ->name($this->getEmailVerificationPromptRouteName());

                Route::get($this->getEmailVerificationRouteSlug('/{id}/{hash}'), $this->getEmailVerificationRouteAction())
                    ->middleware(['signed', 'throttle:6,1'])
                    ->name($this->getEmailVerificationRouteName());
            });
        }

        return $this;
    }
}
