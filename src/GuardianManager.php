<?php

namespace Datalogix\Guardian;

use Closure;
use Datalogix\Guardian\Enums\IdentifierKey;
use Datalogix\Guardian\Events\ServingGuardian;
use Exception;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;

class GuardianManager
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
        return $this->getCurrentOrDefaultFortress()->auth();
    }

    public function user(): ?Authenticatable
    {
        return $this->getCurrentOrDefaultFortress()->user();
    }

    public function canAccess(Model $user): bool
    {
        return $this->getCurrentOrDefaultFortress()->canAccess($user);
    }

    public function cannotAccess(Model $user): bool
    {
        return $this->getCurrentOrDefaultFortress()->cannotAccess($user);
    }

    public function bootCurrentFortress(): void
    {
        if ($this->isCurrentFortressBooted) {
            return;
        }

        $this->getCurrentOrDefaultFortress()->boot();

        $this->isCurrentFortressBooted = true;
    }

    public function getCurrentOrDefaultFortress(): Fortress
    {
        return $this->getCurrentFortress() ?? $this->getDefaultFortress();
    }

    public function getCurrentFortress(): ?Fortress
    {
        return $this->currentFortress;
    }

    public function getDefaultFortress(): Fortress
    {
        return app(FortressRegistry::class)->getDefault();
    }

    public function getFortress(?string $id = null, bool $isStrict = true): Fortress
    {
        return app(FortressRegistry::class)->get($id, $isStrict);
    }

    public function getFortresses(): array
    {
        return app(FortressRegistry::class)->all();
    }

    public function getGuard(): string
    {
        return $this->getCurrentOrDefaultFortress()->getGuard();
    }

    public function getLoginMaxAttempts(): null|int|false
    {
        return $this->getCurrentOrDefaultFortress()->getLoginMaxAttempts();
    }

    public function getIdentifierKey(): IdentifierKey
    {
        return $this->getCurrentOrDefaultFortress()->getIdentifierKey();
    }

    public function getEmailVerificationPromptUrl(array $parameters = []): ?string
    {
        return $this->getCurrentOrDefaultFortress()->getEmailVerificationPromptUrl($parameters);
    }

    public function getEmailVerifiedMiddleware(): ?string
    {
        return $this->getCurrentOrDefaultFortress()->getEmailVerifiedMiddleware();
    }

    public function getPasswordBroker(): ?string
    {
        return $this->getCurrentOrDefaultFortress()->getPasswordBroker();
    }

    public function getPasswordConfirmationMiddleware(): ?string
    {
        return $this->getCurrentOrDefaultFortress()->getPasswordConfirmationMiddleware();
    }

    public function redirect(?string $path = null, bool $intended = false, bool $navigate = true)
    {
        return $this->getCurrentOrDefaultFortress()->redirect($path, $intended, $navigate);
    }

    public function getHomeUrl(): ?string
    {
        return $this->getCurrentOrDefaultFortress()->getHomeUrl() ?? $this->getCurrentOrDefaultFortress()->getUrl();
    }

    public function getId(): string
    {
        return $this->getCurrentOrDefaultFortress()->getId();
    }

    public function getLoginUrl(array $parameters = []): ?string
    {
        return $this->getCurrentOrDefaultFortress()->getLoginUrl($parameters);
    }

    public function getLogoutUrl(array $parameters = []): ?string
    {
        return $this->getCurrentOrDefaultFortress()->getLogoutUrl($parameters);
    }

    public function getForgotPasswordUrl(array $parameters = []): ?string
    {
        return $this->getCurrentOrDefaultFortress()->getForgotPasswordUrl($parameters);
    }

    public function getResetPasswordUrl(string $token, CanResetPassword|Model|Authenticatable $user, array $parameters = []): ?string
    {
        return $this->getCurrentOrDefaultFortress()->getResetPasswordUrl($token, $user, $parameters);
    }

    public function getSignUpUrl(array $parameters = []): ?string
    {
        return $this->getCurrentOrDefaultFortress()->getSignUpUrl($parameters);
    }

    public function getUrl(): ?string
    {
        return $this->getCurrentOrDefaultFortress()->getUrl();
    }

    public function getVerifyEmailUrl(MustVerifyEmail|Model|Authenticatable $user, array $parameters = []): string
    {
        return $this->getCurrentOrDefaultFortress()->getVerifyEmailUrl($user, $parameters);
    }

    public function getLayout(): string
    {
        return $this->getCurrentOrDefaultFortress()->getLayout();
    }

    public function getLayoutForPage(string $page): string
    {
        return $this->getCurrentOrDefaultFortress()->getLayoutForPage($page);
    }

    public function hasLogin(): bool
    {
        return $this->getCurrentOrDefaultFortress()->hasLogin();
    }

    public function hasLogout(): bool
    {
        return $this->getCurrentOrDefaultFortress()->hasLogout();
    }

    public function hasPasswordReset(): bool
    {
        return $this->getCurrentOrDefaultFortress()->hasPasswordReset();
    }

    public function hasSignUp(): bool
    {
        return $this->getCurrentOrDefaultFortress()->hasSignUp();
    }

    public function hasEmailVerification(): bool
    {
        return $this->getCurrentOrDefaultFortress()->hasEmailVerification();
    }

    public function hasPasswordConfirmation(): bool
    {
        return $this->getCurrentOrDefaultFortress()->hasPasswordConfirmation();
    }

    public function wrapInDatabaseTransaction(Closure $callback): mixed
    {
        return $this->getCurrentOrDefaultFortress()->wrapInDatabaseTransaction($callback);
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
        Event::listen(ServingGuardian::class, $callback);
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
                The current domain is not set, but multiple domains are registered for the guardian.
                Please use [Guardian::currentDomain(\'example.com\')] to set the current domain to ensure that guardian URLs are generated correctly.
            ');
        }

        return request()->getHost();
    }
}
