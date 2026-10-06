<?php

namespace Datalogix\Guardian\Tests\Feature\OAuth;

use Datalogix\Guardian\Actions\OAuthCallback;
use Datalogix\Guardian\Enums\AuthFlowResult;
use Datalogix\Guardian\Enums\IdentifierKey;
use Datalogix\Guardian\Exceptions\OAuthException;
use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Tests\Attributes\WithFortresses;
use Datalogix\Guardian\Tests\Feature\OAuth\Concerns\MocksSocialite;
use Datalogix\Guardian\Tests\Fixtures\User;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * A new user of a fortress identified by username gets one generated from the provider.
 */
#[Group('socialite')]
class OAuthCallbackUsernameTest extends TestCase
{
    use MocksSocialite;

    protected function fortresses(): array
    {
        return [Fortress::make()->basic()->identifierKey(IdentifierKey::Username)->emailVerification(isRequired: false)->oauth(providers: ['github'], storeTokens: true)];
    }

    /**
     * Without a transaction around the sign-up, a row inserted while creating the
     * user stays, like one committed by another request.
     */
    protected function withoutTransactions(): array
    {
        return [Fortress::make()->basic()->identifierKey(IdentifierKey::Username)->emailVerification(isRequired: false)->oauth(providers: ['github'])->databaseTransactions(false)];
    }

    protected function tearDown(): void
    {
        Str::createRandomStringsNormally();

        parent::tearDown();
    }

    public function test_it_generates_a_username_for_a_new_user_and_stores_oauth_tokens(): void
    {
        $this->mockSocialiteUser('github', [
            'id' => 'gh-1',
            'email' => 'newbie@example.com',
            'nickname' => 'Cool_Nickname!',
            'token' => 'access-token-value',
            'refreshToken' => 'refresh-token-value',
            'expiresIn' => 3600,
        ]);

        $result = app(OAuthCallback::class)('github');

        $this->assertSame(AuthFlowResult::Authenticated, $result);

        $user = User::whereEmail('newbie@example.com')->firstOrFail();
        $this->assertNotEmpty($user->username);
        $this->assertMatchesRegularExpression('/^[a-z0-9_]+$/', $user->username);

        $identity = DB::table('oauth_identities')->where('provider_user_id', 'gh-1')->first();
        $this->assertNotNull($identity->access_token);
        $this->assertNotNull($identity->refresh_token);
        $this->assertNotNull($identity->token_expires_at);
    }

    public static function usernameSources(): array
    {
        return [
            'the nickname' => [['nickname' => 'The.Nick', 'name' => 'Full Name', 'email' => 'mail@example.com'], 'thenick_'],
            'the name without a nickname' => [['nickname' => null, 'name' => 'Full Name', 'email' => 'mail@example.com'], 'fullname_'],
            'the e-mail without a nickname or name' => [['nickname' => null, 'name' => null, 'email' => 'mail.box@example.com'], 'mailbox_'],
            'the provider when nothing is usable' => [['nickname' => '***', 'name' => null, 'email' => 'x@example.com'], 'github_'],
        ];
    }

    #[DataProvider('usernameSources')]
    public function test_the_username_is_derived_from_the_best_available_source(array $attributes, string $prefix): void
    {
        $this->mockSocialiteUser('github', ['id' => 'gh-source', ...$attributes]);

        app(OAuthCallback::class)('github');

        $this->assertStringStartsWith($prefix, User::whereEmail($attributes['email'])->firstOrFail()->username);
    }

    #[WithFortresses('withoutTransactions')]
    public function test_a_username_taken_in_the_meantime_fails_the_sign_in_without_blaming_the_email(): void
    {
        // Another request takes the generated username between the lookup and the insert.
        User::creating(fn (User $user) => DB::table('users')->insert([
            'name' => 'Racer',
            'email' => 'someone-else@example.com',
            'username' => $user->username,
            'password' => 'x',
        ]));

        $this->mockSocialiteUser('github', ['id' => 'gh-race', 'email' => 'race@example.com', 'nickname' => 'racer']);

        try {
            app(OAuthCallback::class)('github');
            $this->fail('The duplicate username was accepted.');
        } catch (OAuthException $exception) {
            $this->assertSame(OAuthException::unableToAuthenticate()->getMessage(), $exception->getMessage());
            $this->assertGuest();
        }
    }

    public function test_it_retries_with_a_random_suffix_when_the_deterministic_username_is_taken(): void
    {
        // "gh-retry-1" deterministically generates the username
        // "testuser_20aed8" (nickname + first 6 hex chars of sha1(provider id)).
        // Pre-occupying that exact slot forces generateUsername() into its
        // random-retry loop.
        $this->createUser(['username' => 'testuser_20aed8']);

        $this->mockSocialiteUser('github', ['id' => 'gh-retry-1', 'email' => 'retry@example.com', 'nickname' => 'testuser']);

        $result = app(OAuthCallback::class)('github');

        $this->assertSame(AuthFlowResult::Authenticated, $result);

        $user = User::whereEmail('retry@example.com')->firstOrFail();
        $this->assertNotSame('testuser_20aed8', $user->username);
        $this->assertStringStartsWith('testuser_', $user->username);
    }

    public function test_it_falls_back_to_a_fully_random_username_once_every_retry_collides(): void
    {
        // "gh-exhaust-1" deterministically generates "testuser_<sha1 prefix>";
        // forcing every Str::random() call to the same fixed value means all
        // 5 retry-loop candidates collide too, exhausting generateUsername()
        // into its final fully-random fallback.
        $deterministic = 'testuser_'.Str::lower(substr(sha1('gh-exhaust-1'), 0, 6));
        $this->createUser(['username' => $deterministic]);

        Str::createRandomStringsUsing(fn () => 'zzzzzz');
        $this->createUser(['username' => 'testuser_zzzzzz']);

        $this->mockSocialiteUser('github', ['id' => 'gh-exhaust-1', 'email' => 'exhaust@example.com', 'nickname' => 'testuser']);

        $result = app(OAuthCallback::class)('github');

        $this->assertSame(AuthFlowResult::Authenticated, $result);

        $user = User::whereEmail('exhaust@example.com')->firstOrFail();
        $this->assertStringStartsWith('user_', $user->username);
    }
}
