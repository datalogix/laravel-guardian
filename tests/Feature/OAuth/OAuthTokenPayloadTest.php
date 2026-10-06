<?php

namespace Datalogix\Guardian\Tests\Feature\OAuth;

use Datalogix\Guardian\Support\OAuth\OAuthTokenPayload;
use Datalogix\Guardian\Tests\TestCase;
use Laravel\Socialite\Contracts\User as ProviderUser;
use Laravel\Socialite\One\User as OAuthOneUser;
use Laravel\Socialite\Two\User as OAuthTwoUser;
use PHPUnit\Framework\Attributes\Group;

#[Group('socialite')]
class OAuthTokenPayloadTest extends TestCase
{
    public function test_it_extracts_the_payload_from_an_oauth2_user(): void
    {
        $user = OAuthTwoUser::fake(['token' => 'access-token', 'refreshToken' => 'refresh-token', 'expiresIn' => 3600]);

        $payload = (new OAuthTokenPayload)->fromProviderUser($user);

        $this->assertSame('access-token', $payload['access_token']);
        $this->assertSame('refresh-token', $payload['refresh_token']);
        $this->assertNotNull($payload['token_expires_at']);
        $this->assertTrue($payload['token_expires_at']->isFuture());
    }

    public function test_an_oauth2_user_without_tokens_gives_an_empty_payload(): void
    {
        $payload = (new OAuthTokenPayload)->fromProviderUser(new OAuthTwoUser);

        $this->assertSame(['access_token' => null, 'refresh_token' => null, 'token_expires_at' => null], $payload);
    }

    public function test_an_oauth1_user_without_a_token_gives_an_empty_payload(): void
    {
        $payload = (new OAuthTokenPayload)->fromProviderUser(new OAuthOneUser);

        $this->assertSame(['access_token' => null, 'refresh_token' => null, 'token_expires_at' => null], $payload);
    }

    public function test_it_extracts_the_payload_from_an_oauth1_user(): void
    {
        $user = (new OAuthOneUser)->setToken('token-value', 'token-secret');

        $payload = (new OAuthTokenPayload)->fromProviderUser($user);

        $this->assertSame('token-value', $payload['access_token']);
        $this->assertNull($payload['refresh_token']);
        $this->assertNull($payload['token_expires_at']);
    }

    public function test_it_returns_an_empty_payload_for_an_unrecognized_provider_user_type(): void
    {
        $user = new class implements ProviderUser
        {
            public function getId() {}

            public function getNickname() {}

            public function getName() {}

            public function getEmail() {}

            public function getAvatar() {}
        };

        $payload = (new OAuthTokenPayload)->fromProviderUser($user);

        $this->assertSame(['access_token' => null, 'refresh_token' => null, 'token_expires_at' => null], $payload);
    }
}
