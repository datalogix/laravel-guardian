<?php

namespace Datalogix\Guardian;

use Datalogix\Guardian\Exceptions\MultipleDefaultFortressesException;
use Datalogix\Guardian\Exceptions\NoDefaultFortressSetException;
use Datalogix\Guardian\Exceptions\NoFortressRegisteredException;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class FortressRegistry
{
    protected array $fortress = [];

    public ?Fortress $defaultFortress = null;

    public function register(Fortress $fortress): void
    {
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
        $fortress = [];

        foreach ($this->all() as $key => $fortress) {
            $fortress[$normalize($key)] = $fortress;
        }

        return $fortress[$normalize($id)] ?? null;
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
    }
}
