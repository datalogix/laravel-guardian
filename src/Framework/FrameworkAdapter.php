<?php

namespace Datalogix\Guardian\Framework;

use Datalogix\Guardian\Enums\Framework;
use Datalogix\Guardian\Features\Feature;
use Datalogix\Guardian\Fortress;

interface FrameworkAdapter
{
    public function framework(): Framework;

    public function package(): string;

    public function isInstalled(): bool;

    public function pageAction(string $page): string;

    public function registerPageRoutes(Feature $feature, array|string $middleware): void;

    public function registerFortress(Fortress $fortress): void;

    public function validateFortress(Fortress $fortress): void;

    public function redirect(string $path, bool $intended = false, bool $navigate = true): mixed;

    public function notify(string $message, ?string $type = null): void;
}
