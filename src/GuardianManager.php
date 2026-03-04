<?php

namespace Datalogix\Guardian;

use Closure;
use Datalogix\Guardian\Events\FortressBootCompleted;
use Datalogix\Guardian\Events\FortressBootFailed;
use Datalogix\Guardian\Events\FortressBootStarting;
use Datalogix\Guardian\Events\ServingGuardian;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Traits\ForwardsCalls;
use Throwable;

class GuardianManager
{
    use ForwardsCalls;

    protected ?Fortress $currentFortress = null;

    protected ?string $currentDomain = null;

    protected bool $isCurrentFortressBooted = false;

    protected bool $isServing = false;

    public function __construct()
    {
        if (! app()->resolved(FortressRegistry::class)) {
            app(FortressRegistry::class);
        }
    }

    public function registerFortress(Fortress $fortress): void
    {
        app(FortressRegistry::class)->register($fortress);
    }

    public function getFortresses(): array
    {
        return app(FortressRegistry::class)->all();
    }

    public function getCurrentOrDefaultFortress(): Fortress
    {
        return $this->getCurrentFortress() ?? $this->getDefaultFortress();
    }

    public function getCurrentFortress(): ?Fortress
    {
        return $this->currentFortress;
    }

    public function getDefaultFortress(): Fortress
    {
        return app(FortressRegistry::class)->getDefault();
    }

    public function getFortress(?string $id = null, bool $isStrict = true): Fortress
    {
        return app(FortressRegistry::class)->get($id, $isStrict);
    }

    public function setCurrentFortress(?Fortress $fortress): void
    {
        $this->currentFortress = $fortress;
        $this->isCurrentFortressBooted = false;
    }

    public function resetCurrentFortress(): static
    {
        $this->currentFortress = null;
        $this->isCurrentFortressBooted = false;

        return $this;
    }

    public function resetCurrentDomain(): static
    {
        $this->currentDomain = null;

        return $this;
    }

    public function reset(): static
    {
        $this->resetCurrentFortress();
        $this->resetCurrentDomain();
        $this->isServing = false;

        return $this;
    }

    public function setCurrentDomain(?string $domain): void
    {
        $this->currentDomain = $domain;
    }

    public function getCurrentDomain(?string $testingDomain = null): ?string
    {
        if (filled($this->currentDomain)) {
            return $this->currentDomain;
        }

        if (app()->runningUnitTests()) {
            return $testingDomain;
        }

        if (app()->runningInConsole()) {
            return 'localhost';
        }

        return request()->getHost();
    }

    public function bootCurrentFortress(): void
    {
        if ($this->isCurrentFortressBooted) {
            return;
        }

        $fortress = $this->getCurrentOrDefaultFortress();

        event(new FortressBootStarting($fortress));

        try {
            app(FortressRegistry::class)->validate();
            $fortress->boot();

            $this->isCurrentFortressBooted = true;
            event(new FortressBootCompleted($fortress));
        } catch (Throwable $e) {
            event(new FortressBootFailed($fortress, $e));
            throw $e;
        }
    }

    public function isServing(): bool
    {
        return $this->isServing;
    }

    public function serving(Closure $callback): void
    {
        Event::listen(ServingGuardian::class, $callback);
    }

    public function setServingStatus(bool $condition = true): void
    {
        $this->isServing = $condition;
    }

    public function __call($method, $parameters)
    {
        return $this->forwardCallTo(
            $this->getCurrentOrDefaultFortress(),
            $method,
            $parameters
        );
    }
}
