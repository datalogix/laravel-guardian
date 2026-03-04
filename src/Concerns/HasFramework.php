<?php

namespace Datalogix\Guardian\Concerns;

use Datalogix\Guardian\Enums\Framework;

trait HasFramework
{
    protected Framework $framework;

    public function inertia(): static
    {
        return $this->setFramework(Framework::Inertia);
    }

    public function livewire(): static
    {
        return $this->setFramework(Framework::Livewire);
    }

    protected function setFramework(Framework $framework): static
    {
        $this->framework = $framework;

        return $this;
    }

    public function getFramework(): Framework
    {
        if (isset($this->framework)) {
            return $this->framework;
        }

        $config = config('guardian.framework');

        $this->framework = match (true) {
            $config instanceof Framework => $config,
            default => Framework::tryFrom($config) ?? Framework::Livewire,
        };

        return $this->framework;
    }
}
