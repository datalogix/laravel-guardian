<?php

namespace Datalogix\Guardian\Concerns;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\Livewire;
use Livewire\Mechanisms\ComponentRegistry;

trait HasComponents
{
    protected array $livewireComponents = [];

    protected ?bool $hasCachedComponents = null;

    public function livewireComponents($components): static
    {
        if ($this->hasCachedComponents()) {
            return $this;
        }

        $components = array_filter(is_array($components) ? $components : func_get_args());

        foreach ($components as $component) {
            $this->queueLivewireComponentForRegistration($component);
        }

        return $this;
    }

    protected function registerLivewireComponents(): void
    {
        $this->livewireComponents(
            $this->getLoginRouteAction(),
            $this->getLogoutRouteAction(),
            $this->getForgotPasswordRouteAction(),
            $this->getResetPasswordRouteAction(),
            $this->getSignUpRouteAction(),
            $this->getPasswordConfirmationRouteAction(),
            $this->getEmailVerificationPromptRouteAction()
        );

        foreach ($this->livewireComponents as $componentName => $componentClass) {
            Livewire::component($componentName, $componentClass);
        }
    }

    protected function queueLivewireComponentForRegistration(string $component): void
    {
        if (! is_subclass_of($component, Component::class)) {
            return;
        }

        $componentName = app(ComponentRegistry::class)->getName($component);

        $this->livewireComponents[$componentName] = $component;
    }

    public function hasCachedComponents(): bool
    {
        return $this->hasCachedComponents ??= ((! app()->runningInConsole()) && app(Filesystem::class)->exists($this->getComponentCachePath()));
    }

    public function cacheComponents(): void
    {
        $this->hasCachedComponents = false;

        $cachePath = $this->getComponentCachePath();

        $filesystem = app(Filesystem::class);

        $filesystem->ensureDirectoryExists(Str::of($cachePath)->beforeLast(DIRECTORY_SEPARATOR)->toString());

        $filesystem->put(
            $cachePath,
            '<?php return '.var_export([
                'livewireComponents' => $this->livewireComponents,
            ], true).';',
        );

        $this->hasCachedComponents = true;
    }

    public function restoreCachedComponents(): void
    {
        if (! $this->hasCachedComponents()) {
            return;
        }

        $cache = require $this->getComponentCachePath();

        $this->livewireComponents = $cache['livewireComponents'] ?? [];
    }

    public function clearCachedComponents(): void
    {
        app(Filesystem::class)->delete($this->getComponentCachePath());

        $this->hasCachedComponents = false;
    }

    public function getComponentCachePath(): string
    {
        return (config('guardian.cache_path') ?? base_path('bootstrap/cache/guardian')).DIRECTORY_SEPARATOR."{$this->getId()}.php";
    }
}
