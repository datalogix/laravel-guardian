<?php

namespace Datalogix\Fortress\Concerns;

use Illuminate\Filesystem\Filesystem;
use Livewire\Component;
use Livewire\Livewire;
use Livewire\Mechanisms\ComponentRegistry;

trait HasComponents
{
    protected array $livewireComponents = [];

    protected ?bool $hasCachedComponents = null;

    public function livewireComponents(array $components): static
    {
        if ($this->hasCachedComponents()) {
            return $this;
        }

        foreach ($components as $component) {
            $this->queueLivewireComponentForRegistration($component);
        }

        return $this;
    }

    protected function registerLivewireComponents(): void
    {
        if (! $this->hasCachedComponents()) {
            if ($this->hasEmailVerification() && is_subclass_of($emailVerificationRouteAction = $this->getEmailVerificationPromptRouteAction(), Component::class)) {
                $this->queueLivewireComponentForRegistration($emailVerificationRouteAction);
            }

            if ($this->hasLogin() && is_subclass_of($loginRouteAction = $this->getLoginRouteAction(), Component::class)) {
                $this->queueLivewireComponentForRegistration($loginRouteAction);
            }

            if ($this->hasLogout() && is_subclass_of($logoutRouteAction = $this->getLogoutRouteAction(), Component::class)) {
                $this->queueLivewireComponentForRegistration($logoutRouteAction);
            }

            if ($this->hasPasswordReset()) {
                if (is_subclass_of($passwordResetRequestRouteAction = $this->getPasswordResetRequestRouteAction(), Component::class)) {
                    $this->queueLivewireComponentForRegistration($passwordResetRequestRouteAction);
                }

                if (is_subclass_of($passwordResetRouteAction = $this->getPasswordResetRouteAction(), Component::class)) {
                    $this->queueLivewireComponentForRegistration($passwordResetRouteAction);
                }
            }

            if ($this->hasRegistration() && is_subclass_of($registrationRouteAction = $this->getRegistrationRouteAction(), Component::class)) {
                $this->queueLivewireComponentForRegistration($registrationRouteAction);
            }
        }

        foreach ($this->livewireComponents as $componentName => $componentClass) {
            Livewire::component($componentName, $componentClass);
        }
    }

    protected function queueLivewireComponentForRegistration(string $component): void
    {
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

        $filesystem->ensureDirectoryExists((string) str($cachePath)->beforeLast(DIRECTORY_SEPARATOR));

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
        return (config('fortress.cache_path') ?? base_path('bootstrap/cache/fortress')).DIRECTORY_SEPARATOR."{$this->getId()}.php";
    }
}
