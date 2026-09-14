<?php

namespace Datalogix\Guardian\Framework;

use RuntimeException;

class InertiaComponentFactory implements ComponentFactory
{
    public function resolve(string $componentName): string
    {
        throw new RuntimeException(
            "The Inertia framework integration is not implemented yet. Use Framework::Livewire (config('guardian.framework')) instead."
        );
    }
}
