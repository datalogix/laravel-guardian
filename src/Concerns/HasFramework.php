<?php

namespace Datalogix\Guardian\Concerns;

use Datalogix\Guardian\Enums\Framework;

trait HasFramework
{
    protected Framework $framework;

    public function inertia(): static
    {
        $this->framework = Framework::Inertia;

        return $this;
    }

    public function livewire(): static
    {
        $this->framework = Framework::Livewire;

        return $this;
    }

    public function getFramework(): Framework
    {
        return $this->framework ?? Framework::tryFrom(config('guardian.framework'));
    }
}
