<?php

namespace Datalogix\Guardian\Features;

use Closure;
use Datalogix\Guardian\Enums\Layout;
use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Framework\FrameworkResolver;
use Illuminate\Support\Str;

abstract class Feature
{
    protected Fortress $fortress;

    protected string|Closure|array|false|null $routeAction = null;

    protected ?string $routeSlug = null;

    protected ?string $routeName = null;

    protected string|Closure|null $response = null;

    protected int|false|null $maxAttempts = null;

    public function __construct(Fortress $fortress)
    {
        $this->fortress = $fortress;
    }

    public function configure(
        string|Closure|array|false|null $routeAction,
        ?string $routeSlug,
        ?string $routeName,
        string|Closure|null $response,
        int|false|null $maxAttempts,
        Layout|string|null $layout = null,
    ): static {
        $this->routeAction = $routeAction ?? $this->defaultRouteAction();
        $this->routeSlug = $routeSlug ?? $this->defaultRouteSlug();
        $this->routeName = $routeName ?? $this->defaultRouteName();
        $this->response = value($response) ?? value($this->defaultResponse());
        $this->maxAttempts = $maxAttempts ?? $this->defaultMaxAttempts();

        if ($layout) {
            $this->fortress->layoutForPage($this->pageName(), $layout);
        }

        return $this;
    }

    protected function resolveComponent(string $name)
    {
        return app(FrameworkResolver::class)
            ->resolveComponent($name, $this->fortress->getFramework());
    }

    public function getRouteAction(): string|Closure|array|false|null
    {
        return $this->routeAction;
    }

    public function getRouteSlug(): string
    {
        return Str::start($this->routeSlug, '/');
    }

    public function getRouteName(): ?string
    {
        return $this->routeName;
    }

    public function getResponse()
    {
        return value($this->response);
    }

    public function getMaxAttempts(): int|false|null
    {
        return $this->maxAttempts;
    }

    public function hasFeature(): bool
    {
        return filled($this->routeAction);
    }

    public function getUrl(array $parameters = []): ?string
    {
        return $this->hasFeature()
            ? $this->fortress->route($this->getRouteName(), $parameters)
            : null;
    }

    abstract public function registerRoutes(): void;

    abstract protected function defaultRouteAction();

    abstract protected function defaultRouteSlug(): string;

    abstract protected function defaultRouteName(): string;

    abstract protected function defaultResponse(): string;

    abstract protected function defaultMaxAttempts(): int|false;

    abstract protected function pageName(): string;
}
