<?php

namespace Datalogix\Guardian\Exceptions;

class OAuthConfigurationException extends GuardianException
{
    public static function socialiteNotInstalled(string $fortressId): static
    {
        return new static(
            "The Fortress [{$fortressId}] enables the OAuth feature, but Laravel Socialite is not installed. ".
            'Run [composer require laravel/socialite] to use OAuth / social login.'
        );
    }
}
