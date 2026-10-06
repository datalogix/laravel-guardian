<?php

namespace Datalogix\Guardian\Tests\Feature\OAuth;

use Datalogix\Guardian\Actions\OAuthRedirect;
use Datalogix\Guardian\Exceptions\OAuthException;
use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Tests\Attributes\WithFortresses;
use Datalogix\Guardian\Tests\TestCase;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\RedirectResponse;

#[Group('socialite')]
class OAuthRedirectActionTest extends TestCase
{
    protected function fortresses(): array
    {
        return [Fortress::make()->basic()->emailVerification(isRequired: false)->oauth(providers: ['github'])];
    }

    public function test_it_redirects_to_the_provider(): void
    {
        $mock = \Mockery::mock(Provider::class);
        $mock->shouldReceive('redirect')->andReturn(new RedirectResponse('https://github.test/oauth/authorize'));
        Socialite::shouldReceive('driver')->with('github')->andReturn($mock);

        $response = app(OAuthRedirect::class)('github');

        $this->assertSame('https://github.test/oauth/authorize', $response->getTargetUrl());
    }

    public function test_it_throws_for_a_provider_that_is_not_enabled(): void
    {
        $this->expectException(OAuthException::class);
        $this->expectExceptionMessage(OAuthException::providerNotEnabled()->getMessage());

        app(OAuthRedirect::class)('gitlab');
    }

    public function test_it_wraps_driver_failures_in_an_oauth_exception(): void
    {
        Socialite::shouldReceive('driver')->with('github')->andThrow(new \RuntimeException('boom'));

        $this->expectException(OAuthException::class);
        $this->expectExceptionMessage(OAuthException::unableToRedirect()->getMessage());

        app(OAuthRedirect::class)('github');
    }

    protected function enablingBitbucket(): array
    {
        // Configured with credentials here so FortressRegistry::validate() lets the
        // application boot; the test then removes them to exercise the runtime
        // "enabled but not configured" guard in ResolvesOAuthProvider, separately
        // from the boot-time FortressRegistry validation already covered elsewhere.
        config(['services.bitbucket' => [
            'client_id' => 'temporary',
            'client_secret' => 'temporary',
        ]]);

        return [Fortress::make()->basic()->emailVerification(isRequired: false)->oauth(providers: ['bitbucket'])];
    }

    #[WithFortresses('enablingBitbucket')]
    public function test_it_throws_when_an_enabled_provider_has_no_credentials(): void
    {
        config(['services.bitbucket' => []]);

        $this->expectException(OAuthException::class);
        $this->expectExceptionMessage(OAuthException::providerNotConfigured()->getMessage());

        app(OAuthRedirect::class)('bitbucket');
    }
}
