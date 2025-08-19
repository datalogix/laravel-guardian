<?php

namespace Datalogix\Guardian;

use Datalogix\Guardian\Exceptions\NoDefaultFortressSetException;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class FortressRegistry
{
    protected array $fortress = [];

    public function register(Fortress $fortress): void
    {
        $this->fortress[$fortress->getId()] = $fortress;

        $fortress->register();

        if (! $fortress->isDefault()) {
            return;
        }

        if (app()->resolved('guardian')) {
            app('guardian')->setCurrentFortress($fortress);
        }

        app()->resolving('guardian', fn (GuardianManager $manager) => $manager->setCurrentFortress($fortress));
    }

    public function getDefault(): Fortress
    {
        return Arr::first(
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
}
