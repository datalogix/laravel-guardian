<?php

namespace Datalogix\Guardian\Concerns;

use Closure;
use Datalogix\Guardian\Enums\Layout;
use Datalogix\Guardian\Features\EmailVerificationPromptFeature;
use Datalogix\Guardian\Features\EmailVerificationVerifyFeature;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;

trait HasEmailVerification
{
    protected ?EmailVerificationPromptFeature $emailVerificationPromptFeature = null;

    protected ?EmailVerificationVerifyFeature $emailVerificationVerifyFeature = null;

    protected string|Closure|null $emailVerifiedMiddlewareName = null;

    protected ?bool $emailVerificationIsRequired = null;

    public function getEmailVerificationPromptFeature(): EmailVerificationPromptFeature
    {
        return $this->emailVerificationPromptFeature ??= new EmailVerificationPromptFeature($this);
    }

    public function getEmailVerificationVerifyFeature(): EmailVerificationVerifyFeature
    {
        return $this->emailVerificationVerifyFeature ??= new EmailVerificationVerifyFeature($this);
    }

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
        $this->getEmailVerificationPromptFeature()->configure(
            $promptRouteAction,
            $promptRouteSlug,
            $promptRouteName,
            $promptResponse,
            $maxAttempts,
            $promptLayout,
        );

        $this->getEmailVerificationVerifyFeature()->configure(
            $verifyRouteAction,
            $verifyRouteSlug,
            $verifyRouteName,
            $verifyResponse,
            $maxAttempts,
            null,
        );

        $this->emailVerifiedMiddlewareName = $middlewareName ?? 'verified';
        $this->emailVerificationIsRequired = $isRequired ?? true;

        return $this;
    }

    public function getEmailVerifiedMiddlewareName(): ?string
    {
        return value($this->emailVerifiedMiddlewareName);
    }

    public function isEmailVerificationRequired(): ?bool
    {
        return $this->emailVerificationIsRequired;
    }

    public function getVerifyEmailUrl(MustVerifyEmail|Model|Authenticatable $user, array $parameters = []): string
    {
        return URL::temporarySignedRoute(
            $this->generateRouteName($this->getEmailVerificationVerifyFeature()->getRouteName()),
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
            ? "{$this->getEmailVerifiedMiddlewareName()}:{$this->generateRouteName($this->getEmailVerificationPromptFeature()->getRouteName())}"
            : null;
    }

    public function hasEmailVerification(): bool
    {
        return $this->getEmailVerificationPromptFeature()->hasFeature()
            && $this->getEmailVerificationVerifyFeature()->hasFeature();
    }

    public function emailVerificationRoutes(): static
    {
        if ($this->hasEmailVerification()) {
            Route::middleware($this->getAuthMiddleware())->group(function () {
                Route::get($this->getEmailVerificationPromptFeature()->getRouteSlug(), $this->getEmailVerificationPromptFeature()->getRouteAction())
                    ->name($this->getEmailVerificationPromptFeature()->getRouteName());

                Route::get($this->getEmailVerificationVerifyFeature()->getRouteSlug().'/{id}/{hash}', $this->getEmailVerificationVerifyFeature()->getRouteAction())
                    ->middleware(['signed', 'throttle:6,1'])
                    ->name($this->getEmailVerificationVerifyFeature()->getRouteName());
            });
        }

        return $this;
    }
}
