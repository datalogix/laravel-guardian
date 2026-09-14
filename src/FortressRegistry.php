<?php

namespace Datalogix\Guardian;

use Datalogix\Guardian\Enums\Framework;
use Datalogix\Guardian\Enums\IdentifierKey;
use Datalogix\Guardian\Exceptions\EmailVerificationConfigurationException;
use Datalogix\Guardian\Exceptions\FortressIdException;
use Datalogix\Guardian\Exceptions\FrameworkConfigurationException;
use Datalogix\Guardian\Exceptions\IdentifierColumnConfigurationException;
use Datalogix\Guardian\Exceptions\MultipleDefaultFortressesException;
use Datalogix\Guardian\Exceptions\NoDefaultFortressSetException;
use Datalogix\Guardian\Exceptions\NoFortressRegisteredException;
use Datalogix\Guardian\Exceptions\OAuthProviderNotConfiguredException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class FortressRegistry
{
    protected array $fortress = [];

    public ?Fortress $defaultFortress = null;

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

        $this->validateEmailVerificationConfiguration();
        $this->validateOAuthProviderConfiguration();
        $this->validateIdentifierColumnConfiguration();
        $this->validateFrameworkConfiguration();
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
            if ($fortress->getFramework() === Framework::Inertia) {
                throw FrameworkConfigurationException::unimplemented($fortress->getId(), Framework::Inertia->value);
            }
        }
    }
}
