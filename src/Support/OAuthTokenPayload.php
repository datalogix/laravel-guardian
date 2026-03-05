<?php

namespace Datalogix\Guardian\Support;

use Laravel\Socialite\Contracts\User as ProviderUser;

class OAuthTokenPayload
{
    /**
     * @return array{access_token: ?string, refresh_token: ?string, token_expires_at: ?\DateTimeInterface}
     */
    public function fromProviderUser(ProviderUser $oauthUser): array
    {
        $meta = get_object_vars($oauthUser);

        $accessToken = is_string($meta['token'] ?? null) ? $meta['token'] : null;
        $refreshToken = is_string($meta['refreshToken'] ?? null) ? $meta['refreshToken'] : null;
        $tokenExpiresAt = is_int($meta['expiresIn'] ?? null)
            ? now()->addSeconds($meta['expiresIn'])
            : null;

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_expires_at' => $tokenExpiresAt,
        ];
    }
}
