<?php

namespace Datalogix\Guardian\Tests\Feature\OAuth;

use Datalogix\Guardian\Enums\IdentifierKey;
use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('socialite')]
class OAuthCompleteRegistrationMiddlewareTest extends TestCase
{
    protected function fortresses(): array
    {
        return [Fortress::make()->basic()->identifierKey(IdentifierKey::CPF)->emailVerification(isRequired: false)->oauth(providers: ['github'])];
    }

    public function test_the_page_redirects_to_login_without_a_pending_registration(): void
    {
        $response = $this->get('/oauth/complete-registration');

        $response->assertRedirect();
        $this->assertStringContainsString('/login', $response->headers->get('Location'));
    }

    public function test_the_page_is_accessible_with_a_pending_registration(): void
    {
        Guardian::startPendingOAuthRegistration(
            provider: 'github',
            providerUserId: 'gh-1',
            email: 'pending@example.com',
            name: 'Pending',
            avatar: null,
        );

        $response = $this->get('/oauth/complete-registration');

        $response->assertOk();
    }
}
