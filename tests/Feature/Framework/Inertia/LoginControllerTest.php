<?php

namespace Datalogix\Guardian\Tests\Feature\Framework\Inertia;

use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Tests\Attributes\WithFortresses;
use PHPUnit\Framework\Attributes\Group;

#[Group('inertia')]
class LoginControllerTest extends InertiaTestCase
{
    public function test_it_renders_the_login_page_with_its_props(): void
    {
        $response = $this->inertiaGet('/login');

        $this->assertPage($response, 'Guardian/Login', [
            'identifierKey' => 'email',
            'forgotPasswordUrl' => url('/forgot-password'),
            'signUpUrl' => url('/sign-up'),
            'oauthProviders' => [],
            'endpoints.submit' => url('/login'),
            'status' => null,
        ]);
    }

    public function test_submit_authenticates_and_redirects(): void
    {
        $user = $this->createUser(['email' => 'inertia@example.com']);

        $this->inertiaPost('/login', ['login' => 'inertia@example.com', 'password' => 'password'])
            ->assertRedirect();

        $this->assertAuthenticatedAs($user);
    }

    public function test_submit_only_remembers_the_user_when_asked_to(): void
    {
        $this->createUser(['email' => 'inertia@example.com']);
        $recaller = $this->app['auth']->guard()->getRecallerName();

        $forgotten = $this->inertiaPost('/login', ['login' => 'inertia@example.com', 'password' => 'password']);
        $this->assertNotContains($recaller, collect($forgotten->headers->getCookies())->map->getName()->all());

        $this->app['auth']->guard()->logout();

        $remembered = $this->inertiaPost('/login', ['login' => 'inertia@example.com', 'password' => 'password', 'remember' => true]);
        $this->assertContains($recaller, collect($remembered->headers->getCookies())->map->getName()->all());
    }

    public function test_submit_redirects_to_the_intended_url(): void
    {
        $this->createUser(['email' => 'inertia@example.com', 'email_verified_at' => now()]);

        $this->withSession(['url.intended' => url('/dashboard')])
            ->inertiaPost('/login', ['login' => 'inertia@example.com', 'password' => 'password'])
            ->assertRedirect(url('/dashboard'));
    }

    public function test_submit_with_wrong_credentials_returns_validation_errors(): void
    {
        $this->createUser(['email' => 'inertia@example.com']);

        $this->inertiaPost('/login', ['login' => 'inertia@example.com', 'password' => 'wrong'])
            ->assertSessionHasErrors();

        $this->assertGuest();
    }

    public function test_submit_validates_the_payload(): void
    {
        $this->inertiaPost('/login', ['login' => 'not-an-email'])
            ->assertSessionHasErrors(['login', 'password']);
    }

    public function test_authenticated_users_are_redirected_away_from_the_page(): void
    {
        $this->actingAs($this->createUser());

        $this->inertiaGet('/login')->assertRedirect();
    }

    protected function withOAuthProviders(): array
    {
        return [Fortress::make()->inertia()->product()->default()->oauth(providers: ['github'])];
    }

    #[Group('socialite')]
    #[WithFortresses('withOAuthProviders')]
    public function test_the_login_and_sign_up_pages_list_the_oauth_providers(): void
    {
        $providers = [['name' => 'github', 'url' => url('/oauth/github/redirect')]];

        $this->assertPage($this->inertiaGet('/login'), 'Guardian/Login', ['oauthProviders' => $providers]);
        $this->assertPage($this->inertiaGet('/sign-up'), 'Guardian/SignUp', ['oauthProviders' => $providers]);
    }
}
