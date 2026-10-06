<?php

namespace Datalogix\Guardian\Support\OAuth;

use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Support\SessionState;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

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
            'access_token' => $this->encrypt($accessToken),
            'refresh_token' => $this->encrypt($refreshToken),
            'token_expires_at' => $tokenExpiresAt?->format(DATE_ATOM),
            'started_at' => now()->timestamp,
        ]);
    }

    public function get(Fortress $fortress): ?array
    {
        $session = $this->state->getValid(
            $fortress->getOAuthPendingRegistrationSessionKey(),
            $fortress->getOAuthCompleteRegistrationTtl(),
        );

        if ($session === null) {
            return null;
        }

        return [
            ...$session,
            'access_token' => $this->decrypt($session['access_token'] ?? null),
            'refresh_token' => $this->decrypt($session['refresh_token'] ?? null),
        ];
    }

    /**
     * Encrypted here: the session store keeps values as they are unless sessions are encrypted.
     */
    protected function encrypt(?string $token): ?string
    {
        return $token === null ? null : Crypt::encryptString($token);
    }

    protected function decrypt(?string $token): ?string
    {
        if ($token === null) {
            return null;
        }

        try {
            return Crypt::decryptString($token);
        } catch (DecryptException) {
            return null;
        }
    }

    public function clear(Fortress $fortress): void
    {
        $this->state->forget($fortress->getOAuthPendingRegistrationSessionKey());
    }
}
