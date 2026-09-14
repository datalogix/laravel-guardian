<?php

namespace Datalogix\Guardian\Exceptions;

class FrameworkConfigurationException extends GuardianException
{
    public static function unimplemented(string $fortressId, string $framework): static
    {
        return new static(
            "The Fortress [{$fortressId}] is configured to use the [{$framework}] framework integration, ".
            'which is not implemented yet. Use Framework::Livewire instead.'
        );
    }
}
