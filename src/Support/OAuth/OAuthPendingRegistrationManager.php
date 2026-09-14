<?php

namespace Datalogix\Guardian\Support\OAuth;

use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Support\SessionState;

class OAuthPendingRegistrationManager
{
    public function __construct(
        protected SessionState $state,
    ) {}

    public function start(
        Fortress $fortress,
        string $provider,
        string $providerUserId,
        ?string $email,
        ?string $name,
        ?string $avatar,
        bool $emailVerified = false,
        ?string $accessToken = null,
        ?string $refreshToken = null,
        ?\DateTimeInterface $tokenExpiresAt = null,
    ): void {
        $this->state->put($fortress->getOAuthPendingRegistrationSessionKey(), [
            'provider' => $provider,
            'provider_user_id' => $providerUserId,
            'email' => $email,
            'name' => $name,
            'avatar' => $avatar,
            'email_verified' => $emailVerified,
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_expires_at' => $tokenExpiresAt?->format(DATE_ATOM),
            'started_at' => now()->timestamp,
        ]);
    }

    public function get(Fortress $fortress): ?array
    {
        return $this->state->getValid(
            $fortress->getOAuthPendingRegistrationSessionKey(),
            $fortress->getOAuthCompleteRegistrationTtl(),
        );
    }

    public function clear(Fortress $fortress): void
    {
        $this->state->forget($fortress->getOAuthPendingRegistrationSessionKey());
    }
}
