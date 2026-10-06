<?php

namespace Datalogix\Guardian\Tests\Feature\OAuth;

use Datalogix\Guardian\Actions\DisconnectOAuthIdentity;
use Datalogix\Guardian\Events\OAuthIdentityUnlinked;
use Datalogix\Guardian\Exceptions\PasswordConfirmationException;
use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Support\OAuth\OAuthIdentities;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Group;

#[Group('socialite')]
class DisconnectOAuthIdentityActionTest extends TestCase
{
    protected function fortresses(): array
    {
        return [Fortress::make()->basic()->emailVerification(isRequired: false)->oauth(providers: ['github'])];
    }

    public function test_it_requires_a_recently_confirmed_password(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $this->expectException(PasswordConfirmationException::class);
        $this->expectExceptionMessage(PasswordConfirmationException::requiredForDisconnectingOAuth()->getMessage());

        app(DisconnectOAuthIdentity::class)($user, 'github');
    }

    public function test_it_unlinks_the_identity_and_dispatches_an_event(): void
    {
        Event::fake([OAuthIdentityUnlinked::class]);

        $user = $this->createUser();
        $this->actingAs($user);
        session()->put('auth.password_confirmed_at', time());

        app(OAuthIdentities::class)->link(Guardian::getCurrentOrDefaultFortress(), $user, 'github', 'gh-1');

        $result = app(DisconnectOAuthIdentity::class)($user, 'github');

        $this->assertTrue($result);
        $this->assertDatabaseMissing('oauth_identities', ['authenticatable_id' => (string) $user->id, 'provider' => 'github']);
        Event::assertDispatched(OAuthIdentityUnlinked::class);
    }

    public function test_it_returns_false_when_nothing_was_linked(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);
        session()->put('auth.password_confirmed_at', time());

        $result = app(DisconnectOAuthIdentity::class)($user, 'github');

        $this->assertFalse($result);
    }
}
