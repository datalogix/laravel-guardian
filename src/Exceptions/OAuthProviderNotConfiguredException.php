<?php

namespace Datalogix\Guardian\Exceptions;

class OAuthProviderNotConfiguredException extends GuardianException
{
    public static function make(string $fortressId, string $provider): static
    {
        return new static(
            "The Fortress [{$fortressId}] enables the OAuth provider [{$provider}], but ".
            "config('services.{$provider}.client_id') / config('services.{$provider}.client_secret') ".
            'are not set. Add the provider credentials to config/services.php.'
        );
    }
}
