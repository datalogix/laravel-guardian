<?php

namespace Datalogix\Guardian\Actions;

use Datalogix\Guardian\Actions\Concerns\HasRateLimiter;
use Datalogix\Guardian\Actions\Concerns\HasRecentPasswordConfirmation;
use Datalogix\Guardian\Events\OAuthIdentityUnlinked;
use Datalogix\Guardian\Exceptions\OAuthException;
use Datalogix\Guardian\Exceptions\PasswordConfirmationException;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Support\OAuth\OAuthIdentities;
use Illuminate\Database\Eloquent\Model;

class DisconnectOAuthIdentity
{
    use HasRateLimiter;
    use HasRecentPasswordConfirmation;

    public function __construct(
        protected OAuthIdentities $oauthIdentities,
    ) {}

    public function __invoke(Model $user, string $provider): bool
    {
        if (! $this->passwordWasRecentlyConfirmed()) {
            throw PasswordConfirmationException::requiredForDisconnectingOAuth();
        }

        $provider = Guardian::normalizeOAuthProvider($provider);

        return $this->throttleAction(
            function () use ($user, $provider) {
                $fortress = Guardian::getCurrentOrDefaultFortress();
                $unlinked = $this->oauthIdentities->unlink($fortress, $user, $provider);

                if ($unlinked) {
                    event(new OAuthIdentityUnlinked($fortress, $user, $provider));
                }

                return $unlinked;
            },
            fn (int $seconds) => throw OAuthException::rateLimited($seconds),
            $this->userKey($user),
            Guardian::getOAuthFeature()->getMaxAttempts(),
            includeIp: false,
            clearOnSuccess: true,
        );
    }
}
