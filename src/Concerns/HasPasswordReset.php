<?php

namespace Datalogix\Guardian\Concerns;

use Closure;
use Datalogix\Guardian\Enums\IdentifierKey;
use Datalogix\Guardian\Enums\Layout;
use Datalogix\Guardian\Exceptions\IdentifierValueMissingException;
use Datalogix\Guardian\Features\ForgotPasswordFeature;
use Datalogix\Guardian\Features\ResetPasswordFeature;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;

trait HasPasswordReset
{
    protected ?ForgotPasswordFeature $forgotPasswordFeature = null;

    protected ?ResetPasswordFeature $resetPasswordFeature = null;

    protected ?string $passwordBroker = null;

    public function getForgotPasswordFeature(): ForgotPasswordFeature
    {
        return $this->forgotPasswordFeature ??= new ForgotPasswordFeature($this);
    }

    public function getResetPasswordFeature(): ResetPasswordFeature
    {
        return $this->resetPasswordFeature ??= new ResetPasswordFeature($this);
    }

    public function passwordReset(
        string|Closure|array|false|null $forgotPasswordRouteAction = null,
        ?string $forgotPasswordRouteSlug = null,
        ?string $forgotPasswordRouteName = null,
        string|Closure|null $forgotPasswordResponse = null,
        int|false|null $forgotPasswordMaxAttempts = null,
        Layout|string|null $forgotPasswordLayout = null,
        string|Closure|array|false|null $resetPasswordRouteAction = null,
        ?string $resetPasswordRouteSlug = null,
        ?string $resetPasswordRouteName = null,
        string|Closure|null $resetPasswordResponse = null,
        int|false|null $resetPasswordMaxAttempts = null,
        Layout|string|null $resetPasswordLayout = null,
        ?string $passwordBroker = null,
    ): static {
        $this->getForgotPasswordFeature()->configure(
            $forgotPasswordRouteAction,
            $forgotPasswordRouteSlug,
            $forgotPasswordRouteName,
            $forgotPasswordResponse,
            $forgotPasswordMaxAttempts,
            $forgotPasswordLayout,
        );

        $this->getResetPasswordFeature()->configure(
            $resetPasswordRouteAction,
            $resetPasswordRouteSlug,
            $resetPasswordRouteName,
            $resetPasswordResponse,
            $resetPasswordMaxAttempts,
            $resetPasswordLayout,
        );

        $this->passwordBroker = $passwordBroker;

        return $this;
    }

    public function getPasswordBroker(): ?string
    {
        return $this->passwordBroker;
    }

    public function getResetPasswordUrl(string $token, CanResetPassword|Model|Authenticatable $user, array $parameters = []): string
    {
        return URL::signedRoute(
            $this->generateRouteName($this->getResetPasswordFeature()->getRouteName()),
            [
                'login' => $this->resolveResetPasswordIdentifierValue($user),
                'token' => $token,
                ...$parameters,
            ],
        );
    }

    protected function resolveResetPasswordIdentifierValue(CanResetPassword|Model|Authenticatable $user): string
    {
        $identifierKey = $this->getIdentifierKey();

        $value = match ($identifierKey) {
            IdentifierKey::Email => $user->getEmailForPasswordReset(),
            default => data_get($user, $identifierKey->value),
        };

        if (blank($value)) {
            throw IdentifierValueMissingException::forUser($identifierKey->value, $user->getAuthIdentifier());
        }

        return $value;
    }

    public function passwordResetRoutes(): static
    {
        $this->getForgotPasswordFeature()->registerRoutesIfEnabled();
        $this->getResetPasswordFeature()->registerRoutesIfEnabled();

        return $this;
    }

    public function forgotPasswordUrl(): ?string
    {
        return $this->getForgotPasswordFeature()->getUrl();
    }
}
