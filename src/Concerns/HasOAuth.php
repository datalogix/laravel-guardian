<?php

namespace Datalogix\Guardian\Concerns;

use Closure;
use Datalogix\Guardian\Enums\Layout;
use Datalogix\Guardian\Enums\OAuthEmailCollisionPolicy;
use Datalogix\Guardian\Features\OAuthCompleteRegistrationFeature;
use Datalogix\Guardian\Features\OAuthFeature;
use Datalogix\Guardian\Support\OAuth\OAuthPendingRegistrationManager;
use Illuminate\Support\Str;
use InvalidArgumentException;

trait HasOAuth
{
    protected ?OAuthFeature $oauthFeature = null;

    protected ?OAuthCompleteRegistrationFeature $oauthCompleteRegistrationFeature = null;

    protected ?array $oauthProviders = null;

    protected ?bool $oauthAutoLinkByEmail = null;

    protected ?bool $oauthCreateUserIfMissing = null;

    protected ?bool $oauthStateless = null;

    protected ?bool $oauthStoreTokens = null;

    protected ?OAuthEmailCollisionPolicy $oauthEmailCollisionPolicy = null;

    protected int|false|null $oauthCompleteRegistrationTtl = null;

    protected ?Closure $oauthEmailVerifiedUsing = null;

    public function getOAuthFeature(): OAuthFeature
    {
        return $this->oauthFeature ??= new OAuthFeature($this);
    }

    public function getOAuthCompleteRegistrationFeature(): OAuthCompleteRegistrationFeature
    {
        return $this->oauthCompleteRegistrationFeature ??= new OAuthCompleteRegistrationFeature($this);
    }

    public function oauth(
        string|Closure|array|false|null $routeAction = null,
        ?string $routeSlug = null,
        ?string $routeName = null,
        string|Closure|null $response = null,
        int|false|null $maxAttempts = null,
        Layout|string|null $layout = null,
        ?array $providers = null,
        ?bool $autoLinkByEmail = null,
        ?bool $createUserIfMissing = null,
        ?bool $stateless = null,
        ?bool $storeTokens = null,
        ?OAuthEmailCollisionPolicy $emailCollisionPolicy = null,
        int|false|null $completeRegistrationTtl = null,
        ?Closure $emailVerifiedUsing = null,
    ): static {
        $this->getOAuthFeature()->configure(
            $routeAction,
            $routeSlug,
            $routeName,
            $response,
            $maxAttempts,
            $layout,
        );

        $this->getOAuthCompleteRegistrationFeature()->configure(null, null, null, null, null, null);

        $this->oauthProviders = $this->normalizeOAuthProviders($providers);
        $this->oauthCreateUserIfMissing = $createUserIfMissing ?? true;
        $this->oauthStateless = $stateless ?? false;
        $this->oauthStoreTokens = $storeTokens ?? false;
        $this->oauthEmailCollisionPolicy = $emailCollisionPolicy ?? OAuthEmailCollisionPolicy::DenyWithError;
        $this->oauthAutoLinkByEmail = $autoLinkByEmail ?? ($this->oauthEmailCollisionPolicy === OAuthEmailCollisionPolicy::LinkExisting);
        $this->oauthCompleteRegistrationTtl = $completeRegistrationTtl ?? 600;
        $this->oauthEmailVerifiedUsing = $emailVerifiedUsing;

        return $this;
    }

    public function getOAuthProviders(): array
    {
        return $this->oauthProviders ?? [];
    }

    protected function normalizeOAuthProviders(?array $providers): ?array
    {
        if ($providers === null) {
            return null;
        }

        $normalizedProviders = [];

        foreach ($providers as $index => $provider) {
            if (! is_string($provider) || blank($provider)) {
                throw new InvalidArgumentException(
                    "OAuth providers must be a non-empty array of strings. Invalid value at index [{$index}]."
                );
            }

            $normalizedProviders[] = $this->normalizeOAuthProvider($provider);
        }

        return array_values(array_unique($normalizedProviders));
    }

    public function hasOAuthProvider(string $provider): bool
    {
        return in_array($this->normalizeOAuthProvider($provider), $this->getOAuthProviders(), true);
    }

    public function normalizeOAuthProvider(string $provider): string
    {
        return Str::of($provider)->lower()->trim()->toString();
    }

    public function shouldAutoLinkOAuthByEmail(): bool
    {
        return (bool) $this->oauthAutoLinkByEmail;
    }

    public function shouldCreateOAuthUserIfMissing(): bool
    {
        return (bool) $this->oauthCreateUserIfMissing;
    }

    public function isOAuthStateless(): bool
    {
        return (bool) $this->oauthStateless;
    }

    public function shouldStoreOAuthTokens(): bool
    {
        return (bool) $this->oauthStoreTokens;
    }

    public function getOAuthEmailCollisionPolicy(): OAuthEmailCollisionPolicy
    {
        return $this->oauthEmailCollisionPolicy ?? OAuthEmailCollisionPolicy::DenyWithError;
    }

    public function getOAuthEmailVerifiedUsing(): ?Closure
    {
        return $this->oauthEmailVerifiedUsing;
    }

    public function oauthRoutes(): static
    {
        $this->getOAuthFeature()->registerRoutesIfEnabled();
        $this->getOAuthCompleteRegistrationFeature()->registerRoutesIfEnabled();

        return $this;
    }

    public function startPendingOAuthRegistration(
        string $provider,
        string $providerUserId,
        ?string $email,
        ?string $name,
        ?string $avatar,
        bool $emailVerified = false,
        ?string $accessToken = null,
        ?string $refreshToken = null,
        ?\DateTimeInterface $tokenExpiresAt = null,
    ): void {
        $this->oauthPendingRegistrationManager()->start(
            $this,
            $provider,
            $providerUserId,
            $email,
            $name,
            $avatar,
            $emailVerified,
            $accessToken,
            $refreshToken,
            $tokenExpiresAt,
        );
    }

    public function getPendingOAuthRegistrationSession(): ?array
    {
        return $this->oauthPendingRegistrationManager()->get($this);
    }

    public function hasPendingOAuthRegistration(): bool
    {
        return filled($this->getPendingOAuthRegistrationSession());
    }

    public function clearPendingOAuthRegistration(): void
    {
        $this->oauthPendingRegistrationManager()->clear($this);
    }

    public function getOAuthPendingRegistrationSessionKey(): string
    {
        return $this->oauthSessionKey('pending-registration');
    }

    public function getOAuthCompleteRegistrationTtl(): int|false|null
    {
        return $this->oauthCompleteRegistrationTtl;
    }

    protected function oauthSessionKey(string $suffix): string
    {
        return "guardian.{$this->getId()}.oauth.{$suffix}";
    }

    protected function oauthPendingRegistrationManager(): OAuthPendingRegistrationManager
    {
        return app(OAuthPendingRegistrationManager::class);
    }
}
