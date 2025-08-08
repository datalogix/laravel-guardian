<?php

namespace Datalogix\Fortress;

use Datalogix\Fortress\Exceptions\NoDefaultFortressSetException;
use Illuminate\Support\Arr;

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

        if (app()->resolved('fortress')) {
            app('fortress')->setCurrentFortress($fortress);
        }

        app()->resolving('fortress', fn (FortressManager $manager) => $manager->setCurrentFortress($fortress));
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

        $normalize = fn (string $fortressId): string => (string) str($fortressId)
            ->lower()
            ->replace(['-', '_'], '');

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
