<?php

namespace Datalogix\Guardian\Tests\Fixtures\Adapters;

use Datalogix\Guardian\Framework\Livewire\LivewireAdapter;

/**
 * Behaves like the Livewire integration on an app that does not have
 * livewire/livewire installed.
 */
class UninstalledLivewireAdapter extends LivewireAdapter
{
    public function isInstalled(): bool
    {
        return false;
    }
}
