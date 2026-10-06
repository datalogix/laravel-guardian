<?php

namespace Datalogix\Guardian\Concerns;

use Datalogix\Guardian\Http\Middleware\SetUpFortress;

trait HasMiddleware
{
    protected array $middleware = [];

    protected array $authMiddleware = [];

    protected array $excludedMiddleware = [];

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

    /**
     * Without "web" the pages lose their session and CSRF protection.
     */
    public function withoutMiddleware(array $middleware): static
    {
        $this->excludedMiddleware = [
            ...$this->excludedMiddleware,
            ...$middleware,
        ];

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
        $middleware = array_diff(
            [...(config('guardian.middleware') ?? ['web']), ...$this->middleware],
            $this->excludedMiddleware,
        );

        return array_values(array_unique([
            SetUpFortress::class.":{$this->getId()}",
            ...$middleware,
        ]));
    }

    public function getAuthMiddleware(): array
    {
        return $this->authMiddleware;
    }

    public function pullPersistentMiddleware(): array
    {
        $middleware = $this->persistentMiddlewareStack;

        $this->persistentMiddlewareStack = [];

        return $middleware;
    }
}
