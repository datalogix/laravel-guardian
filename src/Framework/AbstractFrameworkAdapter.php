<?php

namespace Datalogix\Guardian\Framework;

use Datalogix\Guardian\Exceptions\FrameworkConfigurationException;
use Datalogix\Guardian\Features\Feature;
use Datalogix\Guardian\Fortress;
use InvalidArgumentException;

abstract class AbstractFrameworkAdapter implements FrameworkAdapter
{
    /**
     * A class that only exists when the framework package is installed.
     */
    abstract protected function requiredClass(): string;

    /**
     * The bundled page name => route action map.
     *
     * @return array<string, string>
     */
    abstract protected function pages(): array;

    public function isInstalled(): bool
    {
        return class_exists($this->requiredClass());
    }

    public function pageAction(string $page): string
    {
        if (! $this->isInstalled()) {
            throw FrameworkConfigurationException::dependencyMissing($this->framework()->value, $this->package());
        }

        return $this->pages()[$page] ?? throw new InvalidArgumentException("Unknown component [{$page}].");
    }

    public function registerPageRoutes(Feature $feature, array|string $middleware): void
    {
        //
    }

    public function registerFortress(Fortress $fortress): void
    {
        //
    }

    public function redirect(string $path, bool $intended = false, bool $navigate = true): mixed
    {
        return $intended
            ? redirect()->intended($path)
            : redirect()->to($path);
    }

    public function notify(string $message, ?string $type = null): void
    {
        session()->flash('status', __($message));
    }
}
