<?php

namespace Datalogix\Guardian\Concerns;

use Closure;
use Datalogix\Guardian\Enums\Framework;
use Datalogix\Guardian\Enums\Layout;
use Datalogix\Guardian\Http\Controllers\EmailVerificationController;
use Datalogix\Guardian\Http\Responses\EmailVerificationPromptResponse;
use Datalogix\Guardian\Http\Responses\EmailVerificationVerifyResponse;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

trait HasEmailVerification
{
    protected string|Closure|array|false|null $emailVerificationPromptRouteAction = null;

    protected ?string $emailVerificationPromptRouteSlug = null;

    protected ?string $emailVerificationPromptRouteName = null;

    protected string|Closure|null $emailVerificationPromptResponse = null;

    protected string|Closure|array|false|null $emailVerificationVerifyRouteAction = null;

    protected ?string $emailVerificationVerifyRouteSlug = null;

    protected ?string $emailVerificationVerifyRouteName = null;

    protected string|Closure|null $emailVerificationVerifyResponse = null;

    protected string|Closure|null $emailVerifiedMiddlewareName = null;

    protected int|false|null $emailVerificationMaxAttempts = null;

    protected ?bool $emailVerificationIsRequired = null;

    public function emailVerification(
        string|Closure|array|false|null $promptRouteAction = null,
        ?string $promptRouteSlug = null,
        ?string $promptRouteName = null,
        string|Closure|null $promptResponse = null,
        Layout|string|null $promptLayout = null,
        string|Closure|array|false|null $verifyRouteAction = null,
        ?string $verifyRouteSlug = null,
        ?string $verifyRouteName = null,
        string|Closure|null $verifyResponse = null,
        string|Closure|null $middlewareName = null,
        ?bool $isRequired = null,
        int|false|null $maxAttempts = null,
    ): static {
        $this->emailVerificationPromptRouteAction = $promptRouteAction ?? match ($this->getFramework()) {
            Framework::Livewire => \Datalogix\Guardian\Http\Livewire\EmailVerificationPrompt::class,
        };
        $this->emailVerificationPromptRouteSlug = $promptRouteSlug ?? 'email-verification/prompt';
        $this->emailVerificationPromptRouteName = $promptRouteName ?? 'auth.email-verification.prompt';
        $this->emailVerificationPromptResponse = $promptResponse ?? EmailVerificationPromptResponse::class;
        $this->layoutForPage('email-verification-prompt', $promptLayout);

        $this->emailVerificationVerifyRouteAction = $verifyRouteAction ?? EmailVerificationController::class;
        $this->emailVerificationVerifyRouteSlug = $verifyRouteSlug ?? 'email-verification/verify';
        $this->emailVerificationVerifyRouteName = $verifyRouteName ?? 'auth.email-verification.verify';
        $this->emailVerificationVerifyResponse = $verifyResponse ?? EmailVerificationVerifyResponse::class;

        $this->emailVerifiedMiddlewareName = $middlewareName ?? 'verified';
        $this->emailVerificationIsRequired = $isRequired ?? true;
        $this->emailVerificationMaxAttempts = $maxAttempts ?? 5;

        return $this;
    }

    public function getEmailVerificationPromptRouteAction(): string|Closure|array|false|null
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

    public function getEmailVerificationPromptResponse()
    {
        return value($this->emailVerificationPromptResponse);
    }

    public function getEmailVerificationVerifyRouteAction(): string|Closure|array|false|null
    {
        return $this->emailVerificationVerifyRouteAction;
    }

    public function getEmailVerificationVerifyRouteSlug(string $suffix): string
    {
        return Str::start($this->emailVerificationVerifyRouteSlug, '/').$suffix;
    }

    public function getEmailVerificationVerifyRouteName(): ?string
    {
        return $this->emailVerificationVerifyRouteName;
    }

    public function getEmailVerificationVerifyResponse()
    {
        return value($this->emailVerificationVerifyResponse);
    }

    public function getEmailVerifiedMiddlewareName(): ?string
    {
        return value($this->emailVerifiedMiddlewareName);
    }

    public function isEmailVerificationRequired(): ?bool
    {
        return $this->emailVerificationIsRequired;
    }

    public function getEmailVerificationMaxAttempts(): int|false|null
    {
        return $this->emailVerificationMaxAttempts;
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
            $this->generateRouteName($this->getEmailVerificationVerifyRouteName()),
            now()->addMinutes(config('auth.verification.expire', 60)),
            [
                'id' => $user->getKey(),
                'hash' => sha1($user->getEmailForVerification()),
                ...$parameters,
            ],
        );
    }

    public function getEmailVerifiedMiddleware(): ?string
    {
        return $this->isEmailVerificationRequired()
            ? "{$this->getEmailVerifiedMiddlewareName()}:{$this->generateRouteName($this->getEmailVerificationPromptRouteName())}"
            : null;
    }

    public function hasEmailVerification(): bool
    {
        return filled($this->getEmailVerificationPromptRouteAction()) && filled($this->getEmailVerificationVerifyRouteAction());
    }

    public function emailVerificationRoutes(): static
    {
        if ($this->hasEmailVerification()) {
            Route::middleware($this->getAuthMiddleware())->group(function () {
                Route::get($this->getEmailVerificationPromptRouteSlug(), $this->getEmailVerificationPromptRouteAction())
                    ->name($this->getEmailVerificationPromptRouteName());

                Route::get($this->getEmailVerificationVerifyRouteSlug('/{id}/{hash}'), $this->getEmailVerificationVerifyRouteAction())
                    ->middleware(['signed', 'throttle:6,1'])
                    ->name($this->getEmailVerificationVerifyRouteName());
            });
        }

        return $this;
    }
}
