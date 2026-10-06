<?php

namespace Datalogix\Guardian\Tests\Feature\OAuth;

use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Support\OAuth\OAuthPendingRegistrationManager;
use Datalogix\Guardian\Tests\TestCase;

class OAuthPendingRegistrationManagerTest extends TestCase
{
    protected OAuthPendingRegistrationManager $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = app(OAuthPendingRegistrationManager::class);
    }

    protected function fortress()
    {
        return Guardian::getCurrentOrDefaultFortress();
    }

    public function test_start_and_get(): void
    {
        $this->manager->start(
            $this->fortress(),
            provider: 'github',
            providerUserId: 'gh-1',
            email: 'pending@example.com',
            name: 'Pending',
            avatar: 'https://example.com/a.png',
        );

        $session = $this->manager->get($this->fortress());

        $this->assertSame('github', $session['provider']);
        $this->assertSame('gh-1', $session['provider_user_id']);
        $this->assertSame('pending@example.com', $session['email']);
    }

    public function test_get_returns_null_when_nothing_is_pending(): void
    {
        $this->assertNull($this->manager->get($this->fortress()));
    }

    public function test_clear_forgets_the_session(): void
    {
        $this->manager->start($this->fortress(), 'github', 'gh-2', null, null, null);

        $this->manager->clear($this->fortress());

        $this->assertNull($this->manager->get($this->fortress()));
    }

    public function test_the_tokens_of_the_provider_are_not_readable_in_the_session_store(): void
    {
        $this->manager->start(
            $this->fortress(), 'github', 'gh-3', 'tokens@example.com', null, null,
            accessToken: 'access-token-value',
            refreshToken: 'refresh-token-value',
        );

        $stored = json_encode(session()->all());
        $this->assertStringNotContainsString('access-token-value', $stored);
        $this->assertStringNotContainsString('refresh-token-value', $stored);

        $session = $this->manager->get($this->fortress());
        $this->assertSame('access-token-value', $session['access_token']);
        $this->assertSame('refresh-token-value', $session['refresh_token']);
    }

    public function test_a_token_that_cannot_be_decrypted_is_dropped(): void
    {
        // For instance one stored before an APP_KEY rotation.
        $this->manager->start($this->fortress(), 'github', 'gh-4', null, null, null, accessToken: 'access-token-value');
        $key = $this->fortress()->getOAuthPendingRegistrationSessionKey();
        session()->put($key, [...session()->get($key), 'access_token' => 'not-encrypted']);

        $session = $this->manager->get($this->fortress());

        $this->assertNull($session['access_token']);
        $this->assertSame('gh-4', $session['provider_user_id']);
    }
}
