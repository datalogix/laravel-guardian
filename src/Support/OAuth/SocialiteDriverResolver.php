<?php

namespace Datalogix\Guardian\Support\OAuth;

use Datalogix\Guardian\Guardian;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;

class SocialiteDriverResolver
{
    public function resolve(string $provider, string $callbackUrl): Provider
    {
        $driver = Socialite::driver($provider);

        if (method_exists($driver, 'redirectUrl')) {
            $driver->redirectUrl($callbackUrl);
        }

        if (Guardian::isOAuthStateless() && method_exists($driver, 'stateless')) {
            $driver = $driver->stateless();
        }

        return $driver;
    }
}
