<?php

namespace Datalogix\Fortress\Concerns;

use Closure;
use Datalogix\Fortress\Facades\Fortress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Livewire\Features\SupportRedirects\Redirector;

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

    public function routes(?Closure $routes): static
    {
        $this->routes[] = $routes;

        return $this;
    }

    public function authenticatedRoutes(Closure $routes): static
    {
        $this->authenticatedRoutes[] = $routes;

        return $this;
    }

    public function route(string $name, mixed $parameters = [], bool $absolute = true): string
    {
        return route($this->generateRouteName($name), $parameters, $absolute);
    }

    public function redirect(?string $path = null, bool $intended = false, bool $navigate = true): RedirectResponse|Redirector|null
    {
        $path ??= $this->getUrl();
        $livewire = app('livewire')?->current();

        if ($livewire) {
            return $intended
                ? $livewire->redirectIntended($path, navigate: $navigate)
                : $livewire->redirect($path, navigate: $navigate);
        }

        return $intended
            ? redirect()->intended($path)
            : redirect()->to($path);
    }

    public function generateRouteName(string $name = '', ?string $domain = ''): string
    {
        if ($this->getId() === 'default') {
            return $name;
        }

        if (count($this->domains) > 1 && filled($domain)) {
            $domain = "{$domain}.";
        }

        if (count($this->domains) > 1 && $domain === '') {
            $domain = Fortress::getCurrentDomain(Arr::first($this->domains)).'.';
        }

        return "fortress.{$this->getId()}.{$domain}{$name}";
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
        return value($this->homeUrl);
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
        if (! $this->auth()->check() && $this->hasLogin()) {
            return $this->getLoginUrl();
        }

        return url($this->getPath());
    }
}
