<?php

namespace Datalogix\Guardian\Support\OAuth;

use Laravel\Socialite\Contracts\User as ProviderUser;
use Laravel\Socialite\One\User as OAuthOneUser;
use Laravel\Socialite\Two\User as OAuthTwoUser;

class OAuthTokenPayload
{
    public function fromProviderUser(ProviderUser $oauthUser): array
    {
        if ($oauthUser instanceof OAuthTwoUser) {
            return [
                'access_token' => is_string($oauthUser->token) ? $oauthUser->token : null,
                'refresh_token' => is_string($oauthUser->refreshToken) ? $oauthUser->refreshToken : null,
                'token_expires_at' => is_int($oauthUser->expiresIn) ? now()->addSeconds($oauthUser->expiresIn) : null,
            ];
        }

        if ($oauthUser instanceof OAuthOneUser) {
            return [
                'access_token' => is_string($oauthUser->token) ? $oauthUser->token : null,
                'refresh_token' => null,
                'token_expires_at' => null,
            ];
        }

        return [
            'access_token' => null,
            'refresh_token' => null,
            'token_expires_at' => null,
        ];
    }
}
