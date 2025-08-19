<?php

namespace Datalogix\Guardian\Concerns;

use Datalogix\Guardian\Enums\IdentifierKey;

trait HasIdentifier
{
    protected IdentifierKey $identifierKey = IdentifierKey::Email;

    public function identifierKey(IdentifierKey|string $identifierKey): static
    {
        $this->identifierKey = is_string($identifierKey)
            ? IdentifierKey::from($identifierKey)
            : $identifierKey;

        return $this;
    }

    public function getIdentifierKey(): IdentifierKey
    {
        return $this->identifierKey;
    }
}
