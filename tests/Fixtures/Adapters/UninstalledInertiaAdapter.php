<?php

namespace Datalogix\Guardian\Tests\Fixtures\Adapters;

use Datalogix\Guardian\Framework\Inertia\InertiaAdapter;

/**
 * Behaves like the Inertia integration on an app that does not have
 * inertiajs/inertia-laravel installed.
 */
class UninstalledInertiaAdapter extends InertiaAdapter
{
    public function isInstalled(): bool
    {
        return false;
    }
}
