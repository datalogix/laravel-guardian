<?php

namespace Datalogix\Guardian\Actions\Concerns;

use Datalogix\Guardian\Exceptions\OAuthException;
use Datalogix\Guardian\Exceptions\OAuthProviderNotConfiguredException;
use Datalogix\Guardian\Guardian;

trait ResolvesOAuthProvider
{
    protected function resolveEnabledOAuthProvider(string $provider): string
    {
        $provider = Guardian::normalizeOAuthProvider($provider);

        if (! Guardian::hasOAuthProvider($provider)) {
            throw OAuthException::providerNotEnabled();
        }

        $hasId = config("services.{$provider}.client_id") || config("services.{$provider}.key");
        $hasSecret = config("services.{$provider}.client_secret") || config("services.{$provider}.secret");

        if (! $hasId || ! $hasSecret) {
            report(OAuthProviderNotConfiguredException::make(Guardian::getCurrentOrDefaultFortress()->getId(), $provider));

            throw OAuthException::providerNotConfigured();
        }

        return $provider;
    }
}
