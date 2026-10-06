<?php

namespace Datalogix\Guardian\Framework;

use Datalogix\Guardian\Enums\Framework;
use Datalogix\Guardian\Exceptions\FrameworkConfigurationException;
use Datalogix\Guardian\Features\Feature;
use Datalogix\Guardian\Fortress;

interface FrameworkAdapter
{
    public function framework(): Framework;

    /**
     * The composer package the bundled pages of this framework depend on.
     */
    public function package(): string;

    public function isInstalled(): bool;

    /**
     * The default route action of a bundled page ('login', 'sign-up', ...).
     *
     * @throws FrameworkConfigurationException when the package is not installed
     */
    public function pageAction(string $page): string;

    /**
     * Registers the routes a bundled page needs besides its GET route
     * (e.g. the POST endpoints of a page that is not self-submitting).
     */
    public function registerPageRoutes(Feature $feature, array|string $middleware): void;

    /**
     * Called when a fortress is registered.
     */
    public function registerFortress(Fortress $fortress): void;

    public function redirect(string $path, bool $intended = false, bool $navigate = true): mixed;

    /**
     * Tells the user about the outcome of an action (a status message).
     */
    public function notify(string $message, ?string $type = null): void;
}
