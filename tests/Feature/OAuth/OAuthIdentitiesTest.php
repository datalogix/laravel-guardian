<?php

namespace Datalogix\Guardian\Tests\Feature\OAuth;

use Datalogix\Guardian\Exceptions\OAuthException;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Support\OAuth\OAuthIdentities;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Support\Facades\DB;

class OAuthIdentitiesTest extends TestCase
{
    protected OAuthIdentities $identities;

    protected function setUp(): void
    {
        parent::setUp();

        $this->identities = new OAuthIdentities;
    }

    protected function fortress()
    {
        return Guardian::getCurrentOrDefaultFortress();
    }

    public function test_is_available_when_the_table_exists(): void
    {
        $this->assertTrue($this->identities->isAvailable());
    }

    public function test_link_and_find_authenticatable_id(): void
    {
        $user = $this->createUser();

        $this->identities->link($this->fortress(), $user, 'github', 'gh-1', 'user@example.com', 'Name', null, 'access-token', 'refresh-token');

        $id = $this->identities->findAuthenticatableId($this->fortress(), 'github', 'gh-1', $user::class);

        $this->assertSame((string) $user->id, (string) $id);
    }

    public function test_find_returns_null_when_not_linked(): void
    {
        $this->assertNull($this->identities->findAuthenticatableId($this->fortress(), 'github', 'unknown', 'App\\Models\\User'));
    }

    public function test_link_encrypts_tokens_and_find_decrypts_them(): void
    {
        $user = $this->createUser();

        $this->identities->link($this->fortress(), $user, 'github', 'gh-2', null, null, null, 'secret-access', 'secret-refresh');

        $stored = DB::table('oauth_identities')->where('provider_user_id', 'gh-2')->first();
        $this->assertStringNotContainsString('secret-access', $stored->access_token);

        $identity = $this->identities->find($this->fortress(), $user, 'github');
        $this->assertSame('secret-access', $identity['access_token']);
        $this->assertSame('secret-refresh', $identity['refresh_token']);
    }

    public function test_link_updates_an_existing_identity_for_the_same_provider(): void
    {
        $user = $this->createUser();

        $this->identities->link($this->fortress(), $user, 'github', 'gh-3');
        $this->identities->link($this->fortress(), $user, 'github', 'gh-3', 'updated@example.com');

        $this->assertSame('updated@example.com', $this->identities->find($this->fortress(), $user, 'github')['email']);
        $this->assertDatabaseCount('oauth_identities', 1);
    }

    public function test_link_throws_when_the_provider_user_id_changes_for_the_same_user(): void
    {
        $user = $this->createUser();

        $this->identities->link($this->fortress(), $user, 'github', 'gh-4');

        $this->expectException(OAuthException::class);
        $this->expectExceptionMessage(OAuthException::identityAlreadyLinked()->getMessage());

        $this->identities->link($this->fortress(), $user, 'github', 'a-different-id');
    }

    public function test_link_throws_when_the_provider_identity_belongs_to_another_existing_user(): void
    {
        $first = $this->createUser();
        $second = $this->createUser();

        $this->identities->link($this->fortress(), $first, 'github', 'gh-5');

        $this->expectException(OAuthException::class);
        $this->expectExceptionMessage(OAuthException::identityAlreadyLinked()->getMessage());

        $this->identities->link($this->fortress(), $second, 'github', 'gh-5');
    }

    public function test_link_releases_a_stale_identity_owned_by_a_deleted_user(): void
    {
        $ghost = $this->createUser();
        $this->identities->link($this->fortress(), $ghost, 'github', 'gh-6');
        $ghostId = $ghost->id;
        $ghost->delete();

        $newOwner = $this->createUser();

        $this->identities->link($this->fortress(), $newOwner, 'github', 'gh-6');

        $this->assertSame(
            (string) $newOwner->id,
            (string) $this->identities->findAuthenticatableId($this->fortress(), 'github', 'gh-6', $newOwner::class)
        );
    }

    public function test_link_releases_a_stale_identity_whose_class_no_longer_exists(): void
    {
        DB::table('oauth_identities')->insert([
            'fortress_id' => $this->fortress()->getId(),
            'auth_guard' => $this->fortress()->getGuard(),
            'authenticatable_type' => 'App\\Models\\NoLongerExists',
            'authenticatable_id' => '999',
            'provider' => 'github',
            'provider_user_id' => 'gh-9',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $newOwner = $this->createUser();

        $this->identities->link($this->fortress(), $newOwner, 'github', 'gh-9');

        $this->assertSame(
            (string) $newOwner->id,
            (string) $this->identities->findAuthenticatableId($this->fortress(), 'github', 'gh-9', $newOwner::class)
        );
    }

    public function test_find_returns_null_tokens_when_they_cannot_be_decrypted(): void
    {
        $user = $this->createUser();
        $this->identities->link($this->fortress(), $user, 'github', 'gh-10', accessToken: 'a-token');

        DB::table('oauth_identities')
            ->where('provider_user_id', 'gh-10')
            ->update(['access_token' => 'not-encrypted-data']);

        $identity = $this->identities->find($this->fortress(), $user, 'github');

        $this->assertNull($identity['access_token']);
    }
}
