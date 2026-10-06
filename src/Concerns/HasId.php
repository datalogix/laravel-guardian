<?php

namespace Datalogix\Guardian\Concerns;

use Datalogix\Guardian\Exceptions\FortressIdException;

trait HasId
{
    public const MAX_ID_LENGTH = 20;

    protected string $id;

    protected bool $idFromPreset = false;

    public function id(string $id): static
    {
        if (isset($this->id) && ! $this->idFromPreset) {
            throw FortressIdException::alreadySet($this->id, $id);
        }

        if (strlen($id) > static::MAX_ID_LENGTH) {
            throw FortressIdException::tooLong($id, static::MAX_ID_LENGTH);
        }

        $this->id = $id;
        $this->idFromPreset = false;

        return $this;
    }

    protected function presetId(string $id): static
    {
        if (! isset($this->id)) {
            $this->id($id);
            $this->idFromPreset = true;
        }

        return $this;
    }

    public function getId(): string
    {
        if (! isset($this->id)) {
            throw FortressIdException::missing();
        }

        return $this->id;
    }
}
