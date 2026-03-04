<?php

namespace Datalogix\Guardian\Concerns;

use Closure;
use Datalogix\Guardian\Guardian;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;

trait HasRoutes
{
    protected array $routes = [];

    protected array $authenticatedRoutes = [];

    protected string|Closure|null $homeUrl = null;

    protected array $domains = [];

    protected string $path = '';

    public function path(string $path): static
    {
        $this->path = $path;

        return $this;
    }

    public function domain(?string $domain): static
    {
        $this->domains(filled($domain) ? [$domain] : []);

        return $this;
    }

    public function domains(array $domains): static
    {
        $this->domains = $domains;

        return $this;
    }

    public function homeUrl(string|Closure|null $url): static
    {
        $this->homeUrl = $url;

        return $this;
    }

    public function routes(Closure $callback, ?bool $requiresAuth = null): static
    {
        if ($requiresAuth) {
            return $this->authenticatedRoutes($callback);
        }

        $this->routes[] = $callback;

        return $this;
    }

    public function authenticatedRoutes(Closure $callback): static
    {
        $this->authenticatedRoutes[] = $callback;

        return $this;
    }

    public function route(string $name, mixed $parameters = [], bool $absolute = true): string
    {
        return route($this->generateRouteName($name), $parameters, $absolute);
    }

    public function generateRouteName(string $name = '', ?string $domain = null): string
    {
        if ($this->getId() === 'default') {
            return $name;
        }

        $domain = $this->resolveDomainForRouteName($domain);

        return "guardian.{$this->getId()}.{$domain}{$name}";
    }

    protected function resolveDomainForRouteName(?string $domain): string
    {
        if (count($this->domains) <= 1) {
            return '';
        }

        if (filled($domain)) {
            return "{$domain}.";
        }

        $resolvedDomain = Guardian::getCurrentDomain(Arr::first($this->domains));

        return "{$resolvedDomain}.";
    }

    public function getRoutes(): array
    {
        return $this->routes;
    }

    public function getAuthenticatedRoutes(): array
    {
        return $this->authenticatedRoutes;
    }

    public function getHomeUrl(): ?string
    {
        return value($this->homeUrl) ?? $this->getUrl();
    }

    public function getDomains(): array
    {
        return Arr::wrap($this->domains);
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getUrl(): ?string
    {
        if (! $this->auth()->check() && $this->getLoginFeature()->hasFeature()) {
            return $this->getLoginFeature()->getUrl();
        }

        return url($this->getPath());
    }

    public function registerRoutes(): static
    {
        foreach ($this->getRoutes() as $callback) {
            $callback($this);
        }

        Route::middleware([
            ...$this->getAuthMiddleware(),
            ...($this->isEmailVerificationRequired() ? [$this->getEmailVerifiedMiddleware()] : []),
        ])->group(function () {
            foreach ($this->getAuthenticatedRoutes() as $callback) {
                $callback($this);
            }
        });

        return $this;
    }
}
