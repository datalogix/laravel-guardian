<?php

namespace Datalogix\Guardian\Tests\Feature\OAuth;

use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Support\OAuth\OAuthIdentities;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Support\Facades\Schema;

class OAuthIdentitiesUnavailableTest extends TestCase
{
    protected OAuthIdentities $identities;

    protected function setUp(): void
    {
        parent::setUp();

        // An app that never enabled OAuth: the migration never ran.
        Schema::dropIfExists('oauth_identities');

        $this->identities = new OAuthIdentities;
    }

    protected function fortress()
    {
        return Guardian::getCurrentOrDefaultFortress();
    }

    public function test_is_available_is_false_without_the_table(): void
    {
        $this->assertFalse($this->identities->isAvailable());
    }

    public function test_find_authenticatable_id_returns_null(): void
    {
        $this->assertNull($this->identities->findAuthenticatableId($this->fortress(), 'github', 'gh-1', 'App\\Models\\User'));
    }

    public function test_link_is_a_no_op(): void
    {
        $this->identities->link($this->fortress(), $this->createUser(), 'github', 'gh-1');

        $this->assertFalse(Schema::hasTable('oauth_identities'));

    }

    public function test_find_returns_null(): void
    {
        $this->assertNull($this->identities->find($this->fortress(), $this->createUser(), 'github'));
    }
}
