<?php

namespace Datalogix\Guardian\Concerns;

use Datalogix\Guardian\Exceptions\FortressIdException;

trait HasId
{
    public const MAX_ID_LENGTH = 20;

    protected string $id;

    public function id(string $id): static
    {
        if (isset($this->id)) {
            throw FortressIdException::alreadyRegistered($this->id);
        }

        if (strlen($id) > static::MAX_ID_LENGTH) {
            throw FortressIdException::tooLong($id, static::MAX_ID_LENGTH);
        }

        $this->id = $id;
        $this->restoreCachedComponents();

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
