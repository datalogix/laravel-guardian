<?php

namespace Datalogix\Guardian\Concerns;

use Closure;

trait HasDefault
{
    protected bool|Closure $isDefault = false;

    public function default(bool|Closure $condition = true): static
    {
        $this->isDefault = $condition;

        return $this;
    }

    public function isDefault(): bool
    {
        return (bool) value($this->isDefault);
    }
}
