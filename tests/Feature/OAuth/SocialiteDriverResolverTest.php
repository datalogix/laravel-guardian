<?php

namespace Datalogix\Guardian\Tests\Feature\OAuth;

use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Support\OAuth\SocialiteDriverResolver;
use Datalogix\Guardian\Tests\Attributes\WithFortresses;
use Datalogix\Guardian\Tests\TestCase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use PHPUnit\Framework\Attributes\Group;

#[Group('socialite')]
class SocialiteDriverResolverTest extends TestCase
{
    protected function fortresses(): array
    {
        return [Fortress::make()->basic()->emailVerification(isRequired: false)->oauth(providers: ['github'])];
    }

    protected function stateless(): array
    {
        return [Fortress::make()->basic()->emailVerification(isRequired: false)->oauth(providers: ['github'], stateless: true)];
    }

    public function test_it_sets_the_callback_url_on_the_driver(): void
    {
        $driver = \Mockery::mock(AbstractProvider::class);
        $driver->shouldReceive('redirectUrl')->once()->with('https://app.test/callback')->andReturnSelf();
        Socialite::shouldReceive('driver')->with('github')->andReturn($driver);

        $resolved = (new SocialiteDriverResolver)->resolve('github', 'https://app.test/callback');

        $this->assertSame($driver, $resolved);
    }

    #[WithFortresses('stateless')]
    public function test_it_switches_the_driver_to_stateless_mode(): void
    {
        $stateless = \Mockery::mock(AbstractProvider::class);

        $driver = \Mockery::mock(AbstractProvider::class);
        $driver->shouldReceive('redirectUrl')->andReturnSelf();
        $driver->shouldReceive('stateless')->once()->andReturn($stateless);

        Socialite::shouldReceive('driver')->with('github')->andReturn($driver);

        $resolved = (new SocialiteDriverResolver)->resolve('github', 'https://app.test/callback');

        $this->assertSame($stateless, $resolved);
    }
}
