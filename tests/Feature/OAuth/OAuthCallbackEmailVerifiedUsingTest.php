<?php

namespace Datalogix\Guardian\Tests\Feature\OAuth;

use Datalogix\Guardian\Actions\OAuthCallback;
use Datalogix\Guardian\Enums\AuthFlowResult;
use Datalogix\Guardian\Enums\OAuthEmailCollisionPolicy;
use Datalogix\Guardian\Exceptions\OAuthException;
use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Tests\Attributes\WithFortresses;
use Datalogix\Guardian\Tests\Feature\OAuth\Concerns\MocksSocialite;
use Datalogix\Guardian\Tests\Fixtures\User;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Group;

#[Group('socialite')]
class OAuthCallbackEmailVerifiedUsingTest extends TestCase
{
    use MocksSocialite;

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('services.discord', [
            'client_id' => 'test-discord-client-id',
            'client_secret' => 'test-discord-client-secret',
            'redirect' => 'http://localhost/oauth/discord/callback',
        ]);

        parent::getEnvironmentSetUp($app);
    }

    protected function fortresses(): array
    {
        return [
            Fortress::make()->basic()->emailVerification(isRequired: false)->oauth(
                providers: ['github'],
                emailCollisionPolicy: OAuthEmailCollisionPolicy::LinkExisting,
                emailVerifiedUsing: fn ($oauthUser, $raw) => $oauthUser->getNickname() === 'trusted',
            ),
        ];
    }

    protected function decidingPerProvider(): array
    {
        return [
            Fortress::make()->basic()->emailVerification(isRequired: false)->oauth(
                providers: ['github', 'discord'],
                emailCollisionPolicy: OAuthEmailCollisionPolicy::LinkExisting,
                // "verified" means the e-mail for Discord; everywhere else the standard claim tells.
                emailVerifiedUsing: fn ($user, array $raw, string $provider) => match ($provider) {
                    'discord' => (bool) ($raw['verified'] ?? false),
                    default => (bool) ($raw['email_verified'] ?? false),
                },
            ),
        ];
    }

    public function test_the_custom_closure_is_used_to_determine_email_verification(): void
    {
        $existing = $this->createUser(['email' => 'closure@example.com']);

        // No verified claim: the closure decides.
        $this->mockSocialiteUser('github', ['id' => 'gh-1', 'email' => 'closure@example.com', 'nickname' => 'trusted']);

        $result = app(OAuthCallback::class)('github');

        $this->assertSame(AuthFlowResult::Authenticated, $result);
        $this->assertTrue(Guardian::user()->is($existing));
    }

    public function test_the_closure_returning_false_still_denies_the_link(): void
    {
        $this->createUser(['email' => 'closure2@example.com']);

        $this->mockSocialiteUser('github', ['id' => 'gh-2', 'email' => 'closure2@example.com', 'nickname' => 'untrusted']);

        $this->expectException(OAuthException::class);
        $this->expectExceptionMessage(OAuthException::emailAlreadyExists()->getMessage());

        app(OAuthCallback::class)('github');
    }

    public function test_the_closure_wins_over_a_verified_claim_of_the_provider(): void
    {
        $this->createUser(['email' => 'claim@example.com']);

        // The provider says the e-mail is verified, the application says it does not trust it.
        $this->mockSocialiteUser('github', ['id' => 'gh-3', 'email' => 'claim@example.com', 'nickname' => 'untrusted', 'email_verified' => true]);

        $this->expectException(OAuthException::class);
        $this->expectExceptionMessage(OAuthException::emailAlreadyExists()->getMessage());

        app(OAuthCallback::class)('github');
    }

    public function test_the_closure_wins_over_an_unverified_claim_of_the_provider(): void
    {
        $existing = $this->createUser(['email' => 'trusting@example.com']);

        $this->mockSocialiteUser('github', ['id' => 'gh-4', 'email' => 'trusting@example.com', 'nickname' => 'trusted', 'email_verified' => false]);

        $this->assertSame(AuthFlowResult::Authenticated, app(OAuthCallback::class)('github'));
        $this->assertTrue(Guardian::user()->is($existing));
    }

    #[WithFortresses('decidingPerProvider')]
    public function test_the_closure_can_trust_a_claim_for_one_provider_only(): void
    {
        $existing = $this->createUser(['email' => 'discord@example.com']);

        $this->mockSocialiteUser('discord', ['id' => 'dc-1', 'email' => 'discord@example.com', 'verified' => true]);

        app(OAuthCallback::class)('discord');

        $this->assertTrue(Guardian::user()->is($existing));
    }

    #[WithFortresses('decidingPerProvider')]
    public function test_the_same_claim_is_not_trusted_for_another_provider(): void
    {
        $this->createUser(['email' => 'github@example.com']);

        $this->mockSocialiteUser('github', ['id' => 'gh-1', 'email' => 'github@example.com', 'verified' => true]);

        $this->expectException(OAuthException::class);
        $this->expectExceptionMessage(OAuthException::emailAlreadyExists()->getMessage());

        app(OAuthCallback::class)('github');
    }

    #[WithFortresses('decidingPerProvider')]
    public function test_the_standard_claim_still_works_for_the_others(): void
    {
        $existing = $this->createUser(['email' => 'standard@example.com']);

        $this->mockSocialiteUser('github', ['id' => 'gh-2', 'email' => 'standard@example.com', 'email_verified' => true]);

        app(OAuthCallback::class)('github');

        $this->assertTrue(Guardian::user()->is($existing));
    }

    #[WithFortresses('decidingPerProvider')]
    public function test_a_new_user_is_created_only_when_its_own_provider_verified_the_email(): void
    {
        $this->mockSocialiteUser('discord', ['id' => 'dc-2', 'email' => 'new-discord@example.com', 'verified' => true]);
        app(OAuthCallback::class)('discord');
        $this->app['auth']->guard()->logout();

        $this->assertNotNull(User::whereEmail('new-discord@example.com')->firstOrFail()->email_verified_at);

        // The same claim means nothing for GitHub, so its e-mail is not taken as verified.
        $this->mockSocialiteUser('github', ['id' => 'gh-3', 'email' => 'new-github@example.com', 'verified' => true]);

        try {
            app(OAuthCallback::class)('github');
            $this->fail('An account was created for an e-mail the provider did not verify.');
        } catch (OAuthException) {
            $this->assertDatabaseMissing('users', ['email' => 'new-github@example.com']);
        }
    }

    #[WithFortresses('decidingPerProvider')]
    public function test_a_closure_that_decided_leaves_nothing_to_warn_about(): void
    {
        Log::spy();
        $this->createUser(['email' => 'quiet@example.com']);

        $this->mockSocialiteUser('github', ['id' => 'gh-4', 'email' => 'quiet@example.com', 'verified' => true]);

        try {
            app(OAuthCallback::class)('github');
            $this->fail('The account was linked.');
        } catch (OAuthException) {
            Log::shouldNotHaveReceived('warning');
        }
    }
}
