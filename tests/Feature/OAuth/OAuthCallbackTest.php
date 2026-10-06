<?php

namespace Datalogix\Guardian\Tests\Feature\OAuth;

use Datalogix\Guardian\Actions\OAuthCallback;
use Datalogix\Guardian\Enums\AuthFlowResult;
use Datalogix\Guardian\Enums\OAuthEmailCollisionPolicy;
use Datalogix\Guardian\Exceptions\OAuthException;
use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Support\OAuth\OAuthIdentities;
use Datalogix\Guardian\Tests\Attributes\WithFortresses;
use Datalogix\Guardian\Tests\Feature\OAuth\Concerns\MocksSocialite;
use Datalogix\Guardian\Tests\Fixtures\NonModelTwoFactorUser;
use Datalogix\Guardian\Tests\Fixtures\User;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use PDOException;
use PHPUnit\Framework\Attributes\Group;
use ReflectionMethod;

#[Group('socialite')]
class OAuthCallbackTest extends TestCase
{
    use MocksSocialite;

    protected function fortresses(): array
    {
        return [Fortress::make()->basic()->emailVerification(isRequired: false)->oauth(providers: ['github'])];
    }

    /**
     * Without a transaction around the sign-up, a row inserted while creating the
     * user stays, like one committed by another request.
     */
    protected function withoutTransactions(): array
    {
        return [Fortress::make()->basic()->emailVerification(isRequired: false)->oauth(providers: ['github'])->databaseTransactions(false)];
    }

    protected function linkingVerifiedEmails(): array
    {
        return [Fortress::make()->basic()->emailVerification(isRequired: false)->oauth(providers: ['github'], emailCollisionPolicy: OAuthEmailCollisionPolicy::LinkExisting)];
    }

    protected function withoutAutoCreate(): array
    {
        return [Fortress::make()->basic()->oauth(providers: ['github'], createUserIfMissing: false)];
    }

    public function test_it_creates_a_new_user_and_logs_them_in(): void
    {
        Event::fake([Registered::class]);

        $this->mockSocialiteUser('github', ['id' => 'gh-1', 'email' => 'new-oauth@example.com', 'name' => 'OAuth User']);

        $result = app(OAuthCallback::class)('github');

        $this->assertSame(AuthFlowResult::Authenticated, $result);
        $this->assertDatabaseHas('users', ['email' => 'new-oauth@example.com']);
        $this->assertTrue(Guardian::isAuthenticated());
        Event::assertDispatched(Registered::class);
    }

    public function test_it_logs_in_a_previously_linked_user(): void
    {
        // First call auto-creates and links the account (no pre-existing user
        // shares this email, so there is no collision to resolve).
        $this->mockSocialiteUser('github', ['id' => 'gh-2', 'email' => 'linked@example.com']);
        app(OAuthCallback::class)('github');

        $user = User::whereEmail('linked@example.com')->firstOrFail();
        $this->assertTrue(Guardian::user()->is($user));
        $this->assertDatabaseHas('oauth_identities', ['provider_user_id' => 'gh-2']);

        // Logging in again through the same provider id reuses the link,
        // bypassing the email-collision policy entirely.
        Guardian::auth()->logout();

        $this->mockSocialiteUser('github', ['id' => 'gh-2', 'email' => 'linked@example.com']);
        app(OAuthCallback::class)('github');

        $this->assertTrue(Guardian::user()->is($user));
        $this->assertDatabaseCount('oauth_identities', 1);
    }

    public function test_a_verified_provider_email_marks_the_new_user_as_verified(): void
    {
        $oauthUser = $this->mockSocialiteUser('github', ['id' => 'gh-verified', 'email' => 'verified@example.com']);
        $oauthUser->setRaw(['email_verified' => true]);

        app(OAuthCallback::class)('github');

        $user = User::whereEmail('verified@example.com')->firstOrFail();
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_a_provider_user_without_raw_claims_creates_an_unverified_user(): void
    {
        $this->mockContractOnlySocialiteUser('github', 'gh-raw', 'no-claims@example.com');

        app(OAuthCallback::class)('github');

        $user = User::whereEmail('no-claims@example.com')->firstOrFail();
        $this->assertNull($user->email_verified_at);
        $this->assertSame('Github User', $user->name);
    }

    #[WithFortresses('linkingVerifiedEmails')]
    public function test_it_rejects_a_user_who_cannot_access_the_fortress(): void
    {
        // The account is found and would be linked: only the access of the user stops it.
        $this->createUser(['email' => 'blocked@example.com', 'can_access' => false]);

        $this->mockSocialiteUser('github', ['id' => 'gh-3', 'email' => 'blocked@example.com', 'email_verified' => true]);

        $this->expectException(OAuthException::class);
        $this->expectExceptionMessage(OAuthException::cannotAccess()->getMessage());

        try {
            app(OAuthCallback::class)('github');
        } finally {
            $this->assertGuest();
            $this->assertDatabaseCount('oauth_identities', 0);
        }
    }

    public function test_a_blocked_user_with_an_already_linked_identity_is_rejected(): void
    {
        $user = $this->createUser(['can_access' => false]);
        app(OAuthIdentities::class)->link(Guardian::getCurrentOrDefaultFortress(), $user, 'github', 'gh-1');

        $this->mockSocialiteUser('github', ['id' => 'gh-1', 'email' => $user->email]);

        $this->expectException(OAuthException::class);
        $this->expectExceptionMessage(OAuthException::cannotAccess()->getMessage());

        app(OAuthCallback::class)('github');
    }

    public function test_it_throws_for_a_disabled_provider(): void
    {
        $this->mockSocialiteUser('github', ['id' => 'gh-4']);

        $this->expectException(OAuthException::class);
        $this->expectExceptionMessage(OAuthException::providerNotEnabled()->getMessage());

        app(OAuthCallback::class)('gitlab');
    }

    public function test_a_blank_provider_user_id_is_rejected(): void
    {
        $this->mockSocialiteUser('github', ['id' => '']);

        $this->expectException(OAuthException::class);
        $this->expectExceptionMessage(OAuthException::unableToAuthenticate()->getMessage());

        app(OAuthCallback::class)('github');
    }

    public function test_it_cannot_create_a_user_without_an_email_from_the_provider(): void
    {
        $this->mockSocialiteUser('github', ['id' => 'gh-1', 'email' => '']);

        $this->expectException(OAuthException::class);
        $this->expectExceptionMessage(OAuthException::noAccountFound()->getMessage());

        app(OAuthCallback::class)('github');
    }

    #[WithFortresses('withoutAutoCreate')]
    public function test_it_throws_no_account_found_instead_of_auto_creating_a_user(): void
    {
        $this->mockSocialiteUser('github', ['id' => 'gh-1', 'email' => 'unknown@example.com']);

        try {
            app(OAuthCallback::class)('github');
            $this->fail('A user was created.');
        } catch (OAuthException $exception) {
            $this->assertSame(OAuthException::noAccountFound()->getMessage(), $exception->getMessage());
            $this->assertDatabaseMissing('users', ['email' => 'unknown@example.com']);
        }
    }

    #[WithFortresses('withoutTransactions')]
    public function test_an_account_created_with_the_same_email_in_the_meantime_is_reported_as_existing(): void
    {
        // Another request creates the account between the lookup and the insert.
        User::creating(fn (User $user) => DB::table('users')->insert([
            'name' => 'Racer',
            'email' => $user->email,
            'password' => 'x',
        ]));

        $this->mockSocialiteUser('github', ['id' => 'gh-race', 'email' => 'race@example.com']);

        try {
            app(OAuthCallback::class)('github');
            $this->fail('The duplicate account was created.');
        } catch (OAuthException $exception) {
            $this->assertSame(OAuthException::emailAlreadyExists()->getMessage(), $exception->getMessage());
            $this->assertGuest();
        }
    }

    public function test_store_identity_is_a_no_op_for_a_non_model_authenticatable(): void
    {
        $method = new ReflectionMethod(OAuthCallback::class, 'storeIdentity');
        $method->setAccessible(true);

        $oauthUser = SocialiteUser::fake(['id' => 'nm-1', 'email' => 'nm@example.com']);

        $method->invoke(app(OAuthCallback::class), 'github', 'nm-1', $oauthUser, new NonModelTwoFactorUser);

        $this->assertDatabaseCount('oauth_identities', 0);
    }

    public function test_store_identity_translates_a_query_exception_into_identity_already_linked(): void
    {
        // OAuthIdentities::link() already prevents duplicate links with its
        // own proactive checks; this exercises the caller's translation of a
        // genuine concurrent-insert failure into a proper OAuthException,
        // without touching that race-prevention logic itself.
        $identities = Mockery::mock(OAuthIdentities::class);
        $identities->shouldReceive('link')->once()->andThrow(
            new QueryException('sqlite', 'insert into "oauth_identities" ...', [], new PDOException('UNIQUE constraint failed'))
        );
        $identities->shouldReceive('findAuthenticatableId')->andReturnNull();
        $this->app->instance(OAuthIdentities::class, $identities);

        $this->mockSocialiteUser('github', ['id' => 'qe-1', 'email' => 'qe@example.com']);

        try {
            app(OAuthCallback::class)('github');
            $this->fail('The concurrent link was not reported.');
        } catch (OAuthException $exception) {
            $this->assertSame(OAuthException::identityAlreadyLinked()->getMessage(), $exception->getMessage());
            $this->assertGuest();
        }
    }
}
