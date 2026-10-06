<?php

namespace Datalogix\Guardian\Features;

use BackedEnum;
use Closure;
use Datalogix\Guardian\Fortress;
use Illuminate\Routing\Route as RouteInstance;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

abstract class Feature
{
    protected Fortress $fortress;

    protected bool $configured = false;

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
        BackedEnum|string|null $layout = null,
    ): static {
        $this->configured = true;
        $this->routeAction = $routeAction;
        $this->routeSlug = $routeSlug ?? $this->defaultRouteSlug();
        $this->routeName = $routeName ?? $this->defaultRouteName();
        $this->response = $response ?? $this->defaultResponse();
        $this->maxAttempts = $maxAttempts ?? $this->defaultMaxAttempts();

        if ($layout) {
            $this->fortress->layoutForPage($this->pageName(), $layout);
        }

        return $this;
    }

    protected function resolveComponent(string $name)
    {
        return $this->fortress->getFrameworkAdapter()->pageAction($name);
    }

    /**
     * The default action is resolved on demand, not when the feature is
     * configured, so the framework of the fortress can still be changed
     * after a preset like basic() enabled the feature.
     */
    public function getRouteAction(): string|Closure|array|false|null
    {
        if ($this->configured && $this->routeAction === null) {
            return $this->defaultRouteAction();
        }

        return $this->routeAction;
    }

    public function getPageName(): string
    {
        return $this->pageName();
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
        $routeAction = $this->getRouteAction();

        return $routeAction !== false && filled($routeAction);
    }

    public function getUrl(array $parameters = []): ?string
    {
        return $this->hasFeature()
            ? $this->fortress->route($this->getRouteName(), $parameters)
            : null;
    }

    public function getEndpointName(string $endpoint): string
    {
        return $this->getRouteName().'.'.$endpoint;
    }

    public function getEndpointUrl(string $endpoint, array $parameters = []): string
    {
        return $this->fortress->route($this->getEndpointName($endpoint), $parameters);
    }

    abstract public function registerRoutes(): void;

    public function registerRoutesIfEnabled(): void
    {
        if ($this->hasFeature()) {
            $this->registerRoutes();
        }
    }

    protected function registerRoute(string $method, string $path, array|string $middleware = []): RouteInstance
    {
        return Route::{$method}($path, $this->getRouteAction())
            ->middleware(array_filter(Arr::wrap($middleware)))
            ->name($this->getRouteName());
    }

    /**
     * Registers the GET route of a bundled page and, when the framework
     * needs them, the endpoints the page submits to.
     */
    protected function registerPageRoute(string $path, array|string $middleware = []): void
    {
        $this->registerRoute('get', $path, $middleware);

        $this->fortress->getFrameworkAdapter()->registerPageRoutes($this, $middleware);
    }

    protected function throttleMiddleware(): ?string
    {
        return $this->getMaxAttempts() ? 'throttle:'.$this->getMaxAttempts().',1' : null;
    }

    abstract protected function defaultRouteAction();

    abstract protected function defaultRouteSlug(): string;

    abstract protected function defaultRouteName(): string;

    abstract protected function defaultResponse(): string;

    abstract protected function defaultMaxAttempts(): int|false;

    abstract protected function pageName(): string;
}
