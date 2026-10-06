<?php

namespace Datalogix\Guardian\Tests\Feature\OAuth\Concerns;

use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Contracts\User as ProviderUser;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

trait MocksSocialite
{
    /**
     * SocialiteUser::fake() only exists in recent versions of Socialite.
     */
    protected function fakeSocialiteUser(array $attributes = []): SocialiteUser
    {
        $attributes = [
            'id' => '123456789',
            'nickname' => 'testuser',
            'name' => 'Test User',
            'email' => 'test@example.com',
            'avatar' => 'https://example.com/avatar.jpg',
            'token' => 'fake-token',
            'refreshToken' => 'fake-refresh-token',
            'expiresIn' => 3600,
            ...$attributes,
        ];

        return (new SocialiteUser)
            ->setRaw($attributes)
            ->map($attributes)
            ->setToken($attributes['token'])
            ->setRefreshToken($attributes['refreshToken'])
            ->setExpiresIn($attributes['expiresIn']);
    }

    protected function mockSocialiteUser(string $provider, array $attributes = []): SocialiteUser
    {
        $user = $this->fakeSocialiteUser($attributes);

        $mock = \Mockery::mock(Provider::class);
        $mock->shouldReceive('user')->andReturn($user);

        Socialite::shouldReceive('driver')->with($provider)->andReturn($mock);

        return $user;
    }

    /**
     * A provider user that only implements the Socialite contract, so it has no raw claims.
     */
    protected function mockContractOnlySocialiteUser(string $provider, string $id, string $email): ProviderUser
    {
        $user = new class($id, $email) implements ProviderUser
        {
            public function __construct(protected string $id, protected string $email) {}

            public function getId()
            {
                return $this->id;
            }

            public function getNickname()
            {
                return null;
            }

            public function getName()
            {
                return null;
            }

            public function getEmail()
            {
                return $this->email;
            }

            public function getAvatar()
            {
                return null;
            }
        };

        $mock = \Mockery::mock(Provider::class);
        $mock->shouldReceive('user')->andReturn($user);

        Socialite::shouldReceive('driver')->with($provider)->andReturn($mock);

        return $user;
    }
}
