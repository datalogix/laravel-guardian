<?php

namespace Datalogix\Guardian\Concerns;

use Exception;

trait HasId
{
    protected string $id;

    public function id(string $id): static
    {
        if (isset($this->id)) {
            throw new Exception("The fortress has already been registered with the ID [{$this->id}].");
        }

        $this->id = $id;
        $this->restoreCachedComponents();

        return $this;
    }

    public function getId(): string
    {
        if (! isset($this->id)) {
            throw new Exception('A fortress has been registered without an `id()`.');
        }

        return $this->id;
    }
}
