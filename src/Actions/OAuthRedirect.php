<?php

namespace Datalogix\Guardian\Actions;

use Datalogix\Guardian\Exceptions\OAuthException;
use Datalogix\Guardian\Guardian;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class OAuthRedirect
{
    public function __invoke(string $provider)
    {
        $provider = Guardian::normalizeOAuthProvider($provider);

        if (! Guardian::hasOAuthProvider($provider)) {
            throw OAuthException::providerNotEnabled();
        }

        try {
            $callbackUrl = Guardian::getOAuthFeature()->getCallbackUrl($provider);
            config(["services.{$provider}.redirect" => $callbackUrl]);

            return $this->driver($provider)->redirect();
        } catch (Throwable) {
            throw OAuthException::unableToRedirect();
        }
    }

    protected function driver(string $provider): Provider
    {
        $driver = Socialite::driver($provider);

        if (Guardian::isOAuthStateless() && method_exists($driver, 'stateless')) {
            $driver = $driver->stateless();
        }

        return $driver;
    }
}
