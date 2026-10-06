<?php

namespace Datalogix\Guardian\Tests\Feature\Http;

use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Tests\Feature\OAuth\Concerns\MocksSocialite;
use Datalogix\Guardian\Tests\TestCase;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\RedirectResponse;

#[Group('socialite')]
class OAuthControllerTest extends TestCase
{
    use MocksSocialite;

    protected function fortresses(): array
    {
        return [Fortress::make()->basic()->emailVerification(isRequired: false)->oauth(providers: ['github'])];
    }

    public function test_redirect_sends_the_user_to_the_provider(): void
    {
        $driver = \Mockery::mock(Provider::class);
        $driver->shouldReceive('redirect')->andReturn(new RedirectResponse('https://github.test/authorize'));
        Socialite::shouldReceive('driver')->with('github')->andReturn($driver);

        $response = $this->get(Guardian::getOAuthFeature()->getRedirectUrl('github'));

        $response->assertRedirect('https://github.test/authorize');
    }

    public function test_redirect_for_a_disabled_provider_redirects_to_login_with_errors(): void
    {
        $response = $this->get('/oauth/gitlab/redirect');

        $response->assertRedirect();
        $this->assertStringContainsString('/login', $response->headers->get('Location'));
        $response->assertSessionHasErrors('oauth');
    }

    public function test_callback_authenticates_and_responds(): void
    {
        $driver = \Mockery::mock(Provider::class);
        $driver->shouldReceive('user')->andReturn($this->fakeSocialiteUser(['id' => 'gh-1', 'email' => 'callback@example.com', 'email_verified' => true]));
        Socialite::shouldReceive('driver')->with('github')->andReturn($driver);

        $response = $this->get(Guardian::getOAuthFeature()->getCallbackUrl('github'));

        $response->assertRedirect();
        $this->assertTrue(Guardian::isAuthenticated());
    }

    public function test_callback_redirects_to_login_with_errors_on_failure(): void
    {
        $driver = \Mockery::mock(Provider::class);
        $driver->shouldReceive('user')->andThrow(new \RuntimeException('provider rejected the request'));
        Socialite::shouldReceive('driver')->with('github')->andReturn($driver);

        $response = $this->get(Guardian::getOAuthFeature()->getCallbackUrl('github'));

        $response->assertRedirect();
        $this->assertStringContainsString('/login', $response->headers->get('Location'));
        $response->assertSessionHasErrors('oauth');
    }
}
