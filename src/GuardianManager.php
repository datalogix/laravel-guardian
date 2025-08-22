<?php

namespace Datalogix\Guardian;

use Closure;
use Datalogix\Guardian\Events\ServingGuardian;
use Exception;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Traits\ForwardsCalls;

class GuardianManager
{
    use ForwardsCalls;

    protected ?Fortress $currentFortress = null;

    protected ?string $currentDomain = null;

    protected bool $isCurrentFortressBooted = false;

    protected bool $isServing = false;

    public function __construct()
    {
        app()->resolved(FortressRegistry::class) || app(FortressRegistry::class);
    }

    public function registerFortress(Fortress $fortress): void
    {
        app(FortressRegistry::class)->register($fortress);
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

    public function getFortresses(): array
    {
        return app(FortressRegistry::class)->all();
    }

    public function setCurrentFortress(?Fortress $fortress): void
    {
        $this->currentFortress = $fortress;
    }

    public function currentDomain(?string $domain): void
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
            throw new Exception('
                The current domain is not set, but multiple domains are registered for the guardian.
                Please use [Guardian::currentDomain(\'example.com\')] to set the current domain to ensure that guardian URLs are generated correctly.
            ');
        }

        return request()->getHost();
    }

    public function bootCurrentFortress(): void
    {
        if ($this->isCurrentFortressBooted) {
            return;
        }

        $this->getCurrentOrDefaultFortress()->boot();

        $this->isCurrentFortressBooted = true;
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
        return $this->forwardCallTo($this->getCurrentOrDefaultFortress(), $method, $parameters);
    }
}
