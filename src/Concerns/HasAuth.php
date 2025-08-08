<?php

namespace Datalogix\Fortress\Concerns;

use Closure;
use Datalogix\Fortress\Actions\Auth\EmailVerification;
use Datalogix\Fortress\Actions\Auth\Logout;
use Datalogix\Fortress\Models\Contracts\FortressUser;
use Datalogix\Fortress\Pages\Auth\EmailVerificationPrompt;
use Datalogix\Fortress\Pages\Auth\Login;
use Datalogix\Fortress\Pages\Auth\PasswordReset;
use Datalogix\Fortress\Pages\Auth\PasswordResetRequest;
use Datalogix\Fortress\Pages\Auth\Register;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

trait HasAuth
{
    protected string|Closure $emailVerifiedMiddlewareName = 'verified';

    protected string|Closure|array|null $emailVerificationPromptRouteAction = null;

    protected string $emailVerificationPromptRouteSlug = 'prompt';

    protected string|Closure|array|null $emailVerificationRouteAction = null;

    protected string $emailVerificationRouteSlug = 'verify';

    protected string $emailVerificationRoutePrefix = 'email-verification';

    protected bool $isEmailVerificationRequired = false;

    protected string|Closure|array|null $loginRouteAction = null;

    protected string $loginRouteSlug = 'login';

    protected string|Closure|array|null $logoutRouteAction = null;

    protected string $logoutRouteSlug = 'logout';

    protected string|Closure|array|null $registrationRouteAction = null;

    protected string $registrationRouteSlug = 'register';

    protected string|Closure|array|null $passwordResetRequestRouteAction = null;

    protected string $passwordResetRequestRouteSlug = 'request';

    protected string|Closure|array|null $passwordResetRouteAction = null;

    protected string $passwordResetRouteSlug = 'reset';

    protected string $passwordResetRoutePrefix = 'reset';

    protected string $authGuard = 'web';

    protected ?string $authPasswordBroker = null;

    protected bool|Closure $arePasswordsRevealable = true;

    public function emailVerification(
        string|Closure|array|null $promptAction = EmailVerificationPrompt::class,
        string|Closure|array|null $action = EmailVerification::class,
        bool $isRequired = true
    ): static {
        $this->emailVerificationPromptRouteAction = $promptAction;
        $this->emailVerificationRouteAction = $action;
        $this->requiresEmailVerification($isRequired);

        return $this;
    }

    public function emailVerificationPromptRouteSlug(string $slug): static
    {
        $this->emailVerificationPromptRouteSlug = $slug;

        return $this;
    }

    public function emailVerificationRouteSlug(string $slug): static
    {
        $this->emailVerificationRouteSlug = $slug;

        return $this;
    }

    public function emailVerificationRoutePrefix(string $prefix): static
    {
        $this->emailVerificationRoutePrefix = $prefix;

        return $this;
    }

    public function emailVerifiedMiddlewareName(string|Closure $name): static
    {
        $this->emailVerifiedMiddlewareName = $name;

        return $this;
    }

    public function requiresEmailVerification(bool $condition = true): static
    {
        $this->isEmailVerificationRequired = $condition;

        return $this;
    }

    public function login(string|Closure|array|null $action = Login::class): static
    {
        $this->loginRouteAction = $action;

        return $this;
    }

    public function loginRouteSlug(string $slug): static
    {
        $this->loginRouteSlug = $slug;

        return $this;
    }

    public function logout(string|Closure|array|null $action = Logout::class): static
    {
        $this->logoutRouteAction = $action;

        return $this;
    }

    public function logoutRouteSlug(string $slug): static
    {
        $this->logoutRouteSlug = $slug;

        return $this;
    }

    public function passwordReset(
        string|Closure|array|null $passwordResetRequestRouteAction = PasswordResetRequest::class,
        string|Closure|array|null $passwordResetRouteAction = PasswordReset::class
    ): static {
        $this->passwordResetRequestRouteAction = $passwordResetRequestRouteAction;
        $this->passwordResetRouteAction = $passwordResetRouteAction;

        return $this;
    }

    public function passwordResetRequestRouteSlug(string $slug): static
    {
        $this->passwordResetRequestRouteSlug = $slug;

        return $this;
    }

    public function passwordResetRouteSlug(string $slug): static
    {
        $this->passwordResetRouteSlug = $slug;

        return $this;
    }

    public function passwordResetRoutePrefix(string $prefix): static
    {
        $this->passwordResetRoutePrefix = $prefix;

        return $this;
    }

    public function registration(string|Closure|array|null $action = Register::class): static
    {
        $this->registrationRouteAction = $action;

        return $this;
    }

    public function registrationRouteSlug(string $slug): static
    {
        $this->registrationRouteSlug = $slug;

        return $this;
    }

    public function canAccess(Model $user): bool
    {
        if (! $user instanceof FortressUser) {
            return true;
        }

        return $user->canAccessFortress($this);
    }

    public function cannotAccess(Model $user): bool
    {
        return ! $this->canAccess($user);
    }

    public function auth(): Guard|StatefulGuard
    {
        return auth()->guard($this->getAuthGuard());
    }

    public function user(): ?Authenticatable
    {
        return $this->auth()->user();
    }

    public function authGuard(string $guard): static
    {
        $this->authGuard = $guard;

        return $this;
    }

    public function authPasswordBroker(?string $broker = null): static
    {
        $this->authPasswordBroker = $broker;

        return $this;
    }

    public function isEmailVerificationRequired(): bool
    {
        return $this->isEmailVerificationRequired;
    }

    public function getEmailVerificationPromptUrl(array $parameters = []): ?string
    {
        if (! $this->hasEmailVerification()) {
            return null;
        }

        return route($this->getEmailVerificationPromptRouteName(), $parameters);
    }

    public function getEmailVerificationPromptRouteName(): string
    {
        return $this->generateRouteName('auth.email-verification.prompt');
    }

    public function getEmailVerifiedMiddleware(): string
    {
        return "{$this->getEmailVerifiedMiddlewareName()}:{$this->getEmailVerificationPromptRouteName()}";
    }

    public function getLoginUrl(array $parameters = []): ?string
    {
        if (! $this->hasLogin()) {
            return null;
        }

        return $this->route('auth.login', $parameters);
    }

    public function getRegistrationUrl(array $parameters = []): ?string
    {
        if (! $this->hasRegistration()) {
            return null;
        }

        return $this->route('auth.register', $parameters);
    }

    public function getPasswordResetRequestUrl(array $parameters = []): ?string
    {
        if (! $this->hasPasswordReset()) {
            return null;
        }

        return $this->route('auth.password.request', $parameters);
    }

    public function getVerifyEmailUrl(MustVerifyEmail|Model|Authenticatable $user, array $parameters = []): string
    {
        return URL::temporarySignedRoute(
            $this->generateRouteName('auth.email-verification.verify'),
            now()->addMinutes(config('auth.verification.expire', 60)),
            [
                'id' => $user->getKey(),
                'hash' => sha1($user->getEmailForVerification()),
                ...$parameters,
            ],
        );
    }

    public function getPasswordResetUrl(string $token, CanResetPassword|Model|Authenticatable $user, array $parameters = []): string
    {
        return URL::signedRoute(
            $this->generateRouteName('auth.password-reset.reset'),
            [
                'email' => $user->getEmailForPasswordReset(),
                'token' => $token,
                ...$parameters,
            ],
        );
    }

    public function getLogoutUrl(array $parameters = []): string
    {
        if (! $this->hasLogout()) {
            return null;
        }

        return $this->route('auth.logout', $parameters);
    }

    public function getEmailVerifiedMiddlewareName(): string
    {
        return value($this->emailVerifiedMiddlewareName);
    }

    public function getEmailVerificationPromptRouteAction(): string|Closure|array|null
    {
        return $this->emailVerificationPromptRouteAction;
    }

    public function getEmailVerificationPromptRouteSlug(): string
    {
        return Str::start($this->emailVerificationPromptRouteSlug, '/');
    }

    public function getEmailVerificationRouteAction(): string|Closure|array|null
    {
        return $this->emailVerificationRouteAction;
    }

    public function getEmailVerificationRouteSlug(string $suffix): string
    {
        return Str::start($this->emailVerificationRouteSlug, '/').$suffix;
    }

    public function getEmailVerificationRoutePrefix(): string
    {
        return Str::start($this->emailVerificationRoutePrefix, '/');
    }

    public function getLoginRouteAction(): string|Closure|array|null
    {
        return $this->loginRouteAction;
    }

    public function getLoginRouteSlug(): string
    {
        return Str::start($this->loginRouteSlug, '/');
    }

    public function getLogoutRouteAction(): string|Closure|array|null
    {
        return $this->logoutRouteAction;
    }

    public function getLogoutRouteSlug(): string
    {
        return Str::start($this->logoutRouteSlug, '/');
    }

    public function getRegistrationRouteAction(): string|Closure|array|null
    {
        return $this->registrationRouteAction;
    }

    public function getRegistrationRouteSlug(): string
    {
        return Str::start($this->registrationRouteSlug, '/');
    }

    public function getPasswordResetRequestRouteAction(): string|Closure|array|null
    {
        return $this->passwordResetRequestRouteAction;
    }

    public function getPasswordResetRequestRouteSlug(): string
    {
        return Str::start($this->passwordResetRequestRouteSlug, '/');
    }

    public function getPasswordResetRouteAction(): string|Closure|array|null
    {
        return $this->passwordResetRouteAction;
    }

    public function getPasswordResetRouteSlug(): string
    {
        return Str::start($this->passwordResetRouteSlug, '/');
    }

    public function getPasswordResetRoutePrefix(): string
    {
        return Str::start($this->passwordResetRoutePrefix, '/');
    }

    public function hasEmailVerification(): bool
    {
        return filled($this->getEmailVerificationPromptRouteAction()) && filled($this->getEmailVerificationRouteAction());
    }

    public function hasLogin(): bool
    {
        return filled($this->getLoginRouteAction());
    }

    public function hasLogout(): bool
    {
        return filled($this->getLogoutRouteAction());
    }

    public function hasPasswordReset(): bool
    {
        return filled($this->getPasswordResetRequestRouteAction());
    }

    public function hasRegistration(): bool
    {
        return filled($this->getRegistrationRouteAction());
    }

    public function getAuthGuard(): string
    {
        return $this->authGuard;
    }

    public function getAuthPasswordBroker(): ?string
    {
        return $this->authPasswordBroker;
    }

    public function revealablePasswords(bool|Closure $condition = true): static
    {
        $this->arePasswordsRevealable = $condition;

        return $this;
    }

    public function arePasswordsRevealable(): bool
    {
        return (bool) value($this->arePasswordsRevealable);
    }
}
