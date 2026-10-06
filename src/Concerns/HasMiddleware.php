<?php

namespace Datalogix\Guardian\Concerns;

use Datalogix\Guardian\Http\Middleware\SetUpFortress;

trait HasMiddleware
{
    protected array $middleware = [];

    protected array $authMiddleware = [];

    protected array $persistentMiddlewareStack = [];

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
        $this->persistentMiddlewareStack = [
            ...$this->persistentMiddlewareStack,
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

    /**
     * The persistent middleware still waiting to be handed to the framework
     * adapter; reading it empties the stack.
     */
    public function pullPersistentMiddleware(): array
    {
        $middleware = $this->persistentMiddlewareStack;

        $this->persistentMiddlewareStack = [];

        return $middleware;
    }
}
