<?php

namespace Datalogix\Guardian\Actions;

use Datalogix\Guardian\Actions\Concerns\ResolvesOAuthProvider;
use Datalogix\Guardian\Exceptions\OAuthException;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Support\OAuth\SocialiteDriverResolver;
use Throwable;

class OAuthRedirect
{
    use ResolvesOAuthProvider;

    public function __construct(
        protected SocialiteDriverResolver $driverResolver,
    ) {
        //
    }

    public function __invoke(string $provider)
    {
        $provider = $this->resolveEnabledOAuthProvider($provider);

        try {
            $callbackUrl = Guardian::getOAuthFeature()->getCallbackUrl($provider);

            return $this->driverResolver->resolve($provider, $callbackUrl)->redirect();
        } catch (Throwable $exception) {
            report($exception);

            throw OAuthException::unableToRedirect();
        }
    }
}
