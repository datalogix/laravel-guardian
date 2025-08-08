<?php

namespace Datalogix\Fortress\Concerns;

use Livewire\Livewire;

trait HasMiddleware
{
    protected array $middleware = [];

    protected array $authMiddleware = [];

    protected array $livewirePersistentMiddleware = [];

    public function middleware(array $middleware, bool $isPersistent = false): static
    {
        $this->middleware = [
            ...$this->middleware,
            ...$middleware,
        ];

        if ($isPersistent) {
            $this->persistentMiddleware($middleware);
        }

        return $this;
    }

    public function authMiddleware(array $middleware, bool $isPersistent = false): static
    {
        $this->authMiddleware = [
            ...$this->authMiddleware,
            ...$middleware,
        ];

        if ($isPersistent) {
            $this->persistentMiddleware($middleware);
        }

        return $this;
    }

    public function persistentMiddleware(array $middleware): static
    {
        $this->livewirePersistentMiddleware = [
            ...$this->livewirePersistentMiddleware,
            ...$middleware,
        ];

        return $this;
    }

    public function getMiddleware(): array
    {
        return [
            "fortress:{$this->getId()}",
            ...$this->middleware,
        ];
    }

    public function getAuthMiddleware(): array
    {
        return $this->authMiddleware;
    }

    protected function registerLivewirePersistentMiddleware(): void
    {
        Livewire::addPersistentMiddleware($this->livewirePersistentMiddleware);

        $this->livewirePersistentMiddleware = [];
    }
}
