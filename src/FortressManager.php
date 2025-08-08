<?php

namespace Datalogix\Fortress;

use Closure;
use Datalogix\Fortress\Events\ServingFortress;
use Exception;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Event;
use Livewire\Features\SupportRedirects\Redirector;

class FortressManager
{
    protected ?string $currentDomain = null;

    protected ?Fortress $currentFortress = null;

    protected bool $isServing = false;

    protected bool $isCurrentFortressBooted = false;

    public function __construct()
    {
        app()->resolved(FortressRegistry::class) || app(FortressRegistry::class);
    }

    public function auth(): Guard|StatefulGuard
    {
        return $this->getCurrentFortress()?->auth();
    }

    public function user(): ?Authenticatable
    {
        return $this->getCurrentFortress()?->user();
    }

    public function canAccess(Model $user): ?bool
    {
        return $this->getCurrentFortress()?->canAccess($user);
    }

    public function cannotAccess(Model $user): ?bool
    {
        return $this->getCurrentFortress()?->cannotAccess($user);
    }

    public function bootCurrentFortress(): void
    {
        if ($this->isCurrentFortressBooted) {
            return;
        }

        $this->getCurrentFortress()?->boot();

        $this->isCurrentFortressBooted = true;
    }

    public function getAuthGuard(): ?string
    {
        return $this->getCurrentFortress()?->getAuthGuard();
    }

    public function getAuthPasswordBroker(): ?string
    {
        return $this->getCurrentFortress()?->getAuthPasswordBroker();
    }

    public function getCurrentFortress(): ?Fortress
    {
        return $this->currentFortress;
    }

    public function getDefaultFortress(): Fortress
    {
        return app(FortressRegistry::class)->getDefault();
    }

    public function getEmailVerificationPromptUrl(array $parameters = []): ?string
    {
        return $this->getCurrentFortress()?->getEmailVerificationPromptUrl($parameters);
    }

    public function getEmailVerifiedMiddleware(): ?string
    {
        return $this->getCurrentFortress()?->getEmailVerifiedMiddleware();
    }

    public function redirect(?string $path = null, bool $intended = false, bool $navigate = true): RedirectResponse|Redirector|null
    {
        return $this->getCurrentFortress()?->redirect($path, $intended, $navigate);
    }

    public function getHomeUrl(): ?string
    {
        return $this->getCurrentFortress()?->getHomeUrl() ?? $this->getCurrentFortress()?->getUrl();
    }

    public function getId(): ?string
    {
        return $this->getCurrentFortress()?->getId();
    }

    public function getLoginUrl(array $parameters = []): ?string
    {
        return $this->getCurrentFortress()?->getLoginUrl($parameters);
    }

    public function getLogoutUrl(array $parameters = []): string
    {
        return $this->getCurrentFortress()?->getLogoutUrl($parameters);
    }

    public function getFortress(?string $id = null, bool $isStrict = true): Fortress
    {
        return app(FortressRegistry::class)->get($id, $isStrict);
    }

    public function getFortresses(): array
    {
        return app(FortressRegistry::class)->all();
    }

    public function getPasswordResetRequestUrl(array $parameters = []): ?string
    {
        return $this->getCurrentFortress()?->getPasswordResetRequestUrl($parameters);
    }

    public function getPasswordResetUrl(string $token, CanResetPassword|Model|Authenticatable $user, array $parameters = []): ?string
    {
        return $this->getCurrentFortress()?->getPasswordResetUrl($token, $user, $parameters);
    }

    public function getUrl(): ?string
    {
        return $this->getCurrentFortress()?->getUrl();
    }

    public function getVerifyEmailUrl(MustVerifyEmail|Model|Authenticatable $user, array $parameters = []): ?string
    {
        return $this->getCurrentFortress()?->getVerifyEmailUrl($user, $parameters);
    }

    public function hasEmailVerification(): ?bool
    {
        return $this->getCurrentFortress()?->hasEmailVerification();
    }

    public function hasLogin(): ?bool
    {
        return $this->getCurrentFortress()?->hasLogin();
    }

    public function hasLogout(): ?bool
    {
        return $this->getCurrentFortress()?->hasLogout();
    }

    public function hasPasswordReset(): ?bool
    {
        return $this->getCurrentFortress()?->hasPasswordReset();
    }

    public function hasRegistration(): ?bool
    {
        return $this->getCurrentFortress()?->hasRegistration();
    }

    public function isServing(): bool
    {
        return $this->isServing;
    }

    public function registerFortress(Fortress $fortress): void
    {
        app(FortressRegistry::class)->register($fortress);
    }

    public function serving(Closure $callback): void
    {
        Event::listen(ServingFortress::class, $callback);
    }

    public function currentDomain(?string $domain): void
    {
        $this->currentDomain = $domain;
    }

    public function setCurrentFortress(?Fortress $fortress): void
    {
        $this->currentFortress = $fortress;
    }

    public function setServingStatus(bool $condition = true): void
    {
        $this->isServing = $condition;
    }

    public function arePasswordsRevealable(): ?bool
    {
        return $this->getCurrentFortress()?->arePasswordsRevealable();
    }

    public function getCurrentDomain(?string $testingDomain = null): ?string
    {
        if (filled($this->currentDomain)) {
            return $this->currentDomain;
        }

        if (app()->runningUnitTests()) {
            return $testingDomain;
        }

        if (app()->runningInConsole()) {
            throw new Exception('
                The current domain is not set, but multiple domains are registered for the fortress.
                Please use [Fortress::currentDomain(\'example.com\')] to set the current domain to ensure that fortress URLs are generated correctly.
            ');
        }

        return request()->getHost();
    }
}
