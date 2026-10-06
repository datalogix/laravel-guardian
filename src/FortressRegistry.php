<?php

namespace Datalogix\Guardian;

use Datalogix\Guardian\Enums\IdentifierKey;
use Datalogix\Guardian\Exceptions\EmailVerificationConfigurationException;
use Datalogix\Guardian\Exceptions\FortressIdException;
use Datalogix\Guardian\Exceptions\FortressRouteCollisionException;
use Datalogix\Guardian\Exceptions\IdentifierColumnConfigurationException;
use Datalogix\Guardian\Exceptions\MultipleDefaultFortressesException;
use Datalogix\Guardian\Exceptions\NoDefaultFortressSetException;
use Datalogix\Guardian\Exceptions\NoFortressRegisteredException;
use Datalogix\Guardian\Exceptions\OAuthConfigurationException;
use Datalogix\Guardian\Exceptions\OAuthProviderNotConfiguredException;
use Datalogix\Guardian\Http\Middleware\SetUpFortress;
use Datalogix\Guardian\Support\Auth\FrameworkVerificationListener;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class FortressRegistry
{
    protected array $fortress = [];

    public ?Fortress $defaultFortress = null;

    /**
     * @var array<string, string> "METHOD domain/uri" => fortress id
     */
    protected array $claimedRoutes = [];

    /**
     * Laravel silently keeps the last of two identical routes, so a collision must fail here.
     */
    public function claimRoutes(Fortress $fortress): void
    {
        $marker = SetUpFortress::class.':'.$fortress->getId();

        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (! in_array($marker, (array) $route->getAction('middleware'), true)) {
                continue;
            }

            foreach ($route->methods() as $method) {
                $key = "{$method} {$route->getDomain()}/".ltrim($route->uri(), '/');
                $owner = $this->claimedRoutes[$key] ?? $fortress->getId();

                if ($owner !== $fortress->getId()) {
                    throw FortressRouteCollisionException::make($key, $owner, $fortress->getId());
                }

                $this->claimedRoutes[$key] = $owner;
            }
        }
    }

    public function register(Fortress $fortress): void
    {
        if (isset($this->fortress[$fortress->getId()])) {
            throw FortressIdException::duplicateInRegistry($fortress->getId());
        }

        $this->fortress[$fortress->getId()] = $fortress;

        $fortress->register();

        if (! $fortress->isDefault()) {
            return;
        }

        $this->resetDefaultFortress();

        if (app()->resolved('guardian')) {
            app('guardian')->setCurrentFortress($fortress);
        }

        app()->resolving('guardian', fn (GuardianManager $manager) => $manager->setCurrentFortress($fortress));
    }

    public function resetDefaultFortress(): void
    {
        $this->defaultFortress = null;
    }

    public function reset(): void
    {
        $this->fortress = [];
        $this->resetDefaultFortress();
    }

    public function getDefault(): Fortress
    {
        return $this->defaultFortress ??= Arr::first(
            $this->all(),
            fn (Fortress $fortress): bool => $fortress->isDefault(),
            fn () => throw NoDefaultFortressSetException::make(),
        );
    }

    public function get(?string $id = null, bool $isStrict = true): Fortress
    {
        return $this->find($id, $isStrict) ?? $this->getDefault();
    }

    protected function find(?string $id = null, bool $isStrict = true): ?Fortress
    {
        if ($id === null) {
            return null;
        }

        if ($isStrict) {
            return $this->fortress[$id] ?? null;
        }

        $normalize = fn (string $fortressId): string => Str::of($fortressId)->lower()->replace(['-', '_'], '')->toString();
        $normalized = [];

        foreach ($this->all() as $key => $fortress) {
            $normalized[$normalize($key)] = $fortress;
        }

        return $normalized[$normalize($id)] ?? null;
    }

    public function all(): array
    {
        return $this->fortress;
    }

    public function validate(): void
    {
        if (count($this->fortress) === 0) {
            throw NoFortressRegisteredException::make();
        }

        $defaults = array_filter(
            $this->all(),
            fn ($fortress) => $fortress->isDefault()
        );

        if (count($defaults) === 0) {
            throw NoDefaultFortressSetException::make();
        }

        if (count($defaults) > 1) {
            throw MultipleDefaultFortressesException::make();
        }

        $this->validateFrameworkConfiguration();
        $this->validateEmailVerificationConfiguration();
        $this->validateOAuthDependencies();
        $this->validateOAuthProviderConfiguration();
        $this->validateVerificationOfNewUsers();

        if ($this->shouldValidateSchema()) {
            $this->validateIdentifierColumnConfiguration();
        }
    }

    /**
     * Not on web requests, where it would query the schema every time.
     */
    protected function shouldValidateSchema(): bool
    {
        return app()->runningInConsole();
    }

    protected function validateEmailVerificationConfiguration(): void
    {
        foreach ($this->all() as $fortress) {
            if (! $fortress->isEmailVerificationRequired()) {
                continue;
            }

            if (! $fortress->getEmailVerificationPromptFeature()->hasFeature()) {
                throw EmailVerificationConfigurationException::missingPromptRoute($fortress->getId());
            }

            if (! $fortress->getEmailVerificationVerifyFeature()->hasFeature()) {
                throw EmailVerificationConfigurationException::missingVerifyRoute($fortress->getId());
            }
        }
    }

    protected function validateVerificationOfNewUsers(): void
    {
        if (! FrameworkVerificationListener::isRegistered()) {
            return;
        }

        foreach ($this->all() as $fortress) {
            $createsUsers = $fortress->getSignUpFeature()->hasFeature()
                || ($fortress->getOAuthFeature()->hasFeature() && $fortress->shouldCreateOAuthUserIfMissing());

            if (! $createsUsers || $fortress->getEmailVerificationVerifyFeature()->hasFeature()) {
                continue;
            }

            $modelClass = $fortress->authModelClass();

            if (is_subclass_of($modelClass, MustVerifyEmail::class)) {
                throw EmailVerificationConfigurationException::missingVerifyRouteForNewUsers($fortress->getId(), $modelClass);
            }
        }
    }

    protected function validateOAuthDependencies(): void
    {
        foreach ($this->all() as $fortress) {
            if ($fortress->getOAuthFeature()->hasFeature() && ! $this->socialiteIsInstalled()) {
                throw OAuthConfigurationException::socialiteNotInstalled($fortress->getId());
            }
        }
    }

    protected function socialiteIsInstalled(): bool
    {
        return class_exists(Socialite::class);
    }

    protected function validateOAuthProviderConfiguration(): void
    {
        foreach ($this->all() as $fortress) {
            if (! $fortress->getOAuthFeature()->hasFeature()) {
                continue;
            }

            foreach ($fortress->getOAuthProviders() as $provider) {
                $hasId = config("services.{$provider}.client_id") || config("services.{$provider}.key");
                $hasSecret = config("services.{$provider}.client_secret") || config("services.{$provider}.secret");

                if (! $hasId || ! $hasSecret) {
                    throw OAuthProviderNotConfiguredException::make($fortress->getId(), $provider);
                }
            }
        }
    }

    protected function validateIdentifierColumnConfiguration(): void
    {
        foreach ($this->all() as $fortress) {
            $modelClass = $fortress->authModelClass();
            $identifierKey = $fortress->getIdentifierKey();
            $model = new $modelClass;
            $connection = Schema::connection($model->getConnectionName());

            // Nothing to check before migrating, or without a database (a build).
            try {
                if (! $connection->hasTable($model->getTable())) {
                    continue;
                }
            } catch (Throwable) {
                continue;
            }

            if (! $connection->hasColumn($model->getTable(), $identifierKey->value)) {
                throw IdentifierColumnConfigurationException::missingColumn($fortress->getId(), $modelClass, $identifierKey->value);
            }

            if ($identifierKey !== IdentifierKey::Email
                && $this->fortressNeedsEmailColumn($fortress)
                && ! $connection->hasColumn($model->getTable(), 'email')) {
                throw IdentifierColumnConfigurationException::missingEmailColumn($fortress->getId(), $modelClass);
            }
        }
    }

    protected function fortressNeedsEmailColumn(Fortress $fortress): bool
    {
        return $fortress->getSignUpFeature()->hasFeature()
            || $fortress->getOAuthFeature()->hasFeature()
            || $fortress->getForgotPasswordFeature()->hasFeature()
            || $fortress->getResetPasswordFeature()->hasFeature()
            || $fortress->isEmailVerificationRequired();
    }

    protected function validateFrameworkConfiguration(): void
    {
        foreach ($this->all() as $fortress) {
            $fortress->getFrameworkAdapter();

            foreach ($fortress->getFeatures() as $feature) {
                // Throws when the framework package of a bundled page is missing.
                $feature->getRouteAction();
            }

            $fortress->getFrameworkAdapter()->validateFortress($fortress);
        }
    }
}
