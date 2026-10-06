<?php

namespace Datalogix\Guardian\Tests\Feature\OAuth;

use Datalogix\Guardian\Actions\OAuthCallback;
use Datalogix\Guardian\Enums\AuthFlowResult;
use Datalogix\Guardian\Enums\IdentifierKey;
use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Tests\Attributes\WithFortresses;
use Datalogix\Guardian\Tests\Feature\OAuth\Concerns\MocksSocialite;
use Datalogix\Guardian\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * A CPF cannot come from the provider, so the user finishes the registration first.
 */
#[Group('socialite')]
class OAuthCallbackPendingRegistrationTest extends TestCase
{
    use MocksSocialite;

    protected function fortresses(): array
    {
        return [Fortress::make()->basic()->identifierKey(IdentifierKey::CPF)->emailVerification(isRequired: false)->oauth(providers: ['github'])];
    }

    protected function storingTokens(): array
    {
        return [Fortress::make()->basic()->identifierKey(IdentifierKey::CPF)->emailVerification(isRequired: false)->oauth(providers: ['github'], storeTokens: true)];
    }

    public function test_a_cpf_identifier_defers_to_a_pending_registration_instead_of_creating_a_user_directly(): void
    {
        $this->mockSocialiteUser('github', ['id' => 'gh-8', 'email' => 'pending@example.com', 'email_verified' => true]);

        $result = app(OAuthCallback::class)('github');

        $this->assertSame(AuthFlowResult::OAuthRegistrationRequired, $result);
        $this->assertTrue(Guardian::hasPendingOAuthRegistration());
        $this->assertDatabaseMissing('users', ['email' => 'pending@example.com']);
        $this->assertNull(Guardian::getPendingOAuthRegistrationSession()['access_token']);
    }

    #[WithFortresses('storingTokens')]
    public function test_pending_registration_captures_the_oauth_tokens_for_later_linking(): void
    {
        $this->mockSocialiteUser('github', [
            'id' => 'gh-1',
            'email' => 'pending@example.com',
            'email_verified' => true,
            'token' => 'access-token-value',
            'refreshToken' => 'refresh-token-value',
            'expiresIn' => 3600,
        ]);

        $result = app(OAuthCallback::class)('github');

        $this->assertSame(AuthFlowResult::OAuthRegistrationRequired, $result);

        $session = Guardian::getPendingOAuthRegistrationSession();
        $this->assertSame('access-token-value', $session['access_token']);
        $this->assertSame('refresh-token-value', $session['refresh_token']);
        $this->assertNotNull($session['token_expires_at']);
    }
}
