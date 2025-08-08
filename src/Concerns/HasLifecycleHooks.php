<?php

namespace Datalogix\Fortress\Concerns;

use Closure;

trait HasLifecycleHooks
{
    protected array $bootCallbacks = [];

    public function boot(): void
    {
        foreach ($this->bootCallbacks as $callback) {
            $callback($this);
        }
    }

    public function bootUsing(Closure $callback): static
    {
        $this->bootCallbacks[] = $callback;

        return $this;
    }

    public function register(): void
    {
        $this->registerLivewireComponents();
        $this->registerLivewirePersistentMiddleware();
    }
}
