<?php

namespace Datalogix\Guardian\Concerns;

use Closure;
use Datalogix\Guardian\Enums\Layout;
use Datalogix\Guardian\Enums\OAuthEmailCollisionPolicy;
use Datalogix\Guardian\Features\OAuthFeature;
use InvalidArgumentException;
use Illuminate\Support\Str;

trait HasOAuth
{
    protected ?OAuthFeature $oauthFeature = null;

    protected ?array $oauthProviders = null;

    protected ?bool $oauthAutoLinkByEmail = null;

    protected ?bool $oauthCreateUserIfMissing = null;

    protected ?bool $oauthStateless = null;

    protected ?bool $oauthStoreTokens = null;

    protected ?OAuthEmailCollisionPolicy $oauthEmailCollisionPolicy = null;

    public function getOAuthFeature(): OAuthFeature
    {
        return $this->oauthFeature ??= new OAuthFeature($this);
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
    ): static {
        $this->getOAuthFeature()->configure(
            $routeAction,
            $routeSlug,
            $routeName,
            $response,
            $maxAttempts,
            $layout,
        );

        $this->oauthProviders = $this->normalizeOAuthProviders($providers);
        $this->oauthAutoLinkByEmail = $autoLinkByEmail;
        $this->oauthCreateUserIfMissing = $createUserIfMissing ?? true;
        $this->oauthStateless = $stateless ?? false;
        $this->oauthStoreTokens = $storeTokens ?? false;
        $this->oauthEmailCollisionPolicy = $emailCollisionPolicy ?? OAuthEmailCollisionPolicy::LinkExisting;

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
        return $this->oauthEmailCollisionPolicy ?? OAuthEmailCollisionPolicy::LinkExisting;
    }

    public function oauthRoutes(): static
    {
        if ($this->getOAuthFeature()->hasFeature()) {
            $this->getOAuthFeature()->registerRoutes();
        }

        return $this;
    }
}
