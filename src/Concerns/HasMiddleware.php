<?php

namespace Datalogix\Guardian\Concerns;

use Datalogix\Guardian\Http\Middleware\SetUpFortress;
use Livewire\Livewire;

trait HasMiddleware
{
    protected array $middleware = [];

    protected array $authMiddleware = [];

    protected array $livewirePersistentMiddleware = [];

    public function middleware(array $middleware, bool $isPersistent = false): static
    {
        return $this->appendMiddleware('middleware', $middleware, $isPersistent);
    }

    public function authMiddleware(array $middleware, bool $isPersistent = false): static
    {
        return $this->appendMiddleware('authMiddleware', $middleware, $isPersistent);
    }

    protected function appendMiddleware(string $property, array $middleware, bool $isPersistent): static
    {
        $this->{$property} = [
            ...$this->{$property},
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
            SetUpFortress::class.":{$this->getId()}",
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
