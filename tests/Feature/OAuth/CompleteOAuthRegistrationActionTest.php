<?php

namespace Datalogix\Guardian\Tests\Feature\OAuth;

use Datalogix\Guardian\Actions\CompleteOAuthRegistration;
use Datalogix\Guardian\Enums\AuthFlowResult;
use Datalogix\Guardian\Enums\IdentifierKey;
use Datalogix\Guardian\Exceptions\OAuthException;
use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Support\OAuth\OAuthIdentities;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Mockery;
use PDOException;
use PHPUnit\Framework\Attributes\Group;

#[Group('socialite')]
class CompleteOAuthRegistrationActionTest extends TestCase
{
    protected function fortresses(): array
    {
        return [Fortress::make()->basic()->identifierKey(IdentifierKey::CPF)->emailVerification(isRequired: false)->oauth(providers: ['github'])];
    }

    protected function startPendingRegistration(): void
    {
        Guardian::startPendingOAuthRegistration(
            provider: 'github',
            providerUserId: 'gh-100',
            email: 'pending-user@example.com',
            name: 'Pending User',
            avatar: null,
            emailVerified: true,
        );
    }

    public function test_it_throws_without_a_pending_registration(): void
    {
        $this->expectException(OAuthException::class);
        $this->expectExceptionMessage(OAuthException::registrationNotPending()->getMessage());

        app(CompleteOAuthRegistration::class)(['login' => '529.982.247-25']);
    }

    public function test_it_creates_the_user_and_links_the_identity(): void
    {
        $this->startPendingRegistration();

        $result = app(CompleteOAuthRegistration::class)(['login' => '529.982.247-25']);

        $this->assertSame(AuthFlowResult::Authenticated, $result);
        $this->assertDatabaseHas('users', ['email' => 'pending-user@example.com', 'cpf' => '529.982.247-25']);
        $this->assertDatabaseHas('oauth_identities', ['provider_user_id' => 'gh-100']);
        $this->assertTrue(Guardian::isAuthenticated());
        $this->assertFalse(Guardian::hasPendingOAuthRegistration());
    }

    public function test_the_tokens_captured_by_the_provider_are_kept_on_the_linked_identity(): void
    {
        Guardian::startPendingOAuthRegistration(
            provider: 'github',
            providerUserId: 'gh-101',
            email: 'with-tokens@example.com',
            name: 'Token User',
            avatar: null,
            emailVerified: true,
            accessToken: 'access-token-value',
            refreshToken: 'refresh-token-value',
            tokenExpiresAt: now()->addHour(),
        );

        app(CompleteOAuthRegistration::class)(['login' => '529.982.247-25']);

        $identity = DB::table('oauth_identities')->where('provider_user_id', 'gh-101')->first();
        $this->assertNotNull($identity->access_token);
        $this->assertNotNull($identity->refresh_token);
        $this->assertNotNull($identity->token_expires_at);
    }

    public function test_rules_validate_the_login_field(): void
    {
        $this->startPendingRegistration();

        $rules = CompleteOAuthRegistration::rules();

        $this->assertArrayHasKey('login', $rules);
    }

    public function test_a_query_exception_while_linking_the_identity_is_translated_into_identity_already_linked(): void
    {
        // OAuthIdentities::link() already prevents duplicate links with its
        // own proactive checks; this exercises the action's translation of a
        // genuine concurrent-insert failure into a proper OAuthException,
        // without touching that race-prevention logic itself.
        Guardian::startPendingOAuthRegistration(
            provider: 'github',
            providerUserId: 'gh-200',
            email: 'race@example.com',
            name: 'Race User',
            avatar: null,
            emailVerified: true,
        );

        $identities = Mockery::mock(OAuthIdentities::class);
        $identities->shouldReceive('link')->once()->andThrow(
            new QueryException('sqlite', 'insert into "oauth_identities" ...', [], new PDOException('UNIQUE constraint failed'))
        );
        $this->app->instance(OAuthIdentities::class, $identities);

        $this->expectException(OAuthException::class);
        $this->expectExceptionMessage(OAuthException::identityAlreadyLinked()->getMessage());

        app(CompleteOAuthRegistration::class)(['login' => '529.982.247-25']);
    }
}
