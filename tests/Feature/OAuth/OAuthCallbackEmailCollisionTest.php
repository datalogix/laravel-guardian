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
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

#[Group('socialite')]
class OAuthCallbackEmailCollisionTest extends TestCase
{
    use MocksSocialite;

    protected function fortresses(): array
    {
        return [
            Fortress::make()->basic()->emailVerification(isRequired: false)->oauth(
                providers: ['github'],
                emailCollisionPolicy: OAuthEmailCollisionPolicy::LinkExisting,
            ),
        ];
    }

    protected function denyingCollisions(): array
    {
        return [Fortress::make()->basic()->emailVerification(isRequired: false)->oauth(providers: ['github'], emailCollisionPolicy: OAuthEmailCollisionPolicy::DenyWithError)];
    }

    protected function requiringManualLinks(): array
    {
        return [Fortress::make()->basic()->emailVerification(isRequired: false)->oauth(providers: ['github'], emailCollisionPolicy: OAuthEmailCollisionPolicy::RequireManualLink)];
    }

    protected function withoutAutoLinking(): array
    {
        return [
            Fortress::make()->basic()->emailVerification(isRequired: false)->oauth(
                providers: ['github'],
                autoLinkByEmail: false,
                emailCollisionPolicy: OAuthEmailCollisionPolicy::LinkExisting,
            ),
        ];
    }

    public static function trustedClaims(): array
    {
        return [
            'email_verified' => [['email_verified' => true]],
            'verified_email' => [['verified_email' => true]],
            'a boolean sent as text' => [['email_verified' => 'true']],
        ];
    }

    public static function untrustedClaims(): array
    {
        return [
            'an unverified claim' => [['email_verified' => false]],
            'a boolean sent as text' => [['verified_email' => 'false']],
            'a verified account is not a verified e-mail' => [['verified' => true]],
            'no claim at all' => [[]],
        ];
    }

    protected function refusedWith(array $claims): void
    {
        $this->createUser(['email' => 'owner@example.com']);
        $this->mockSocialiteUser('github', ['id' => 'gh-1', 'email' => 'owner@example.com', ...$claims]);

        try {
            app(OAuthCallback::class)('github');
            $this->fail('The account was linked.');
        } catch (OAuthException) {
            $this->assertGuest();
        }
    }

    public function test_it_links_the_existing_account_when_the_provider_email_is_verified(): void
    {
        $existing = $this->createUser(['email' => 'existing@example.com']);

        $user = $this->mockSocialiteUser('github', ['id' => 'gh-6', 'email' => 'existing@example.com']);
        $user->setRaw(['email_verified' => true]);

        $result = app(OAuthCallback::class)('github');

        $this->assertSame(AuthFlowResult::Authenticated, $result);
        $this->assertTrue(Guardian::user()->is($existing));
    }

    #[DataProvider('trustedClaims')]
    public function test_the_existing_account_is_linked_when_the_provider_vouches_for_the_email(array $claims): void
    {
        $existing = $this->createUser(['email' => 'owner@example.com']);

        $this->mockSocialiteUser('github', ['id' => 'gh-1', 'email' => 'owner@example.com', ...$claims]);

        app(OAuthCallback::class)('github');

        $this->assertTrue(Guardian::user()->is($existing));
    }

    #[DataProvider('untrustedClaims')]
    public function test_the_existing_account_is_not_linked_otherwise(array $claims): void
    {
        $this->refusedWith($claims);
    }

    public function test_a_refusal_because_of_an_ignored_verified_claim_tells_what_to_configure(): void
    {
        // The log says why the user was refused.
        Log::spy();

        $this->refusedWith(['verified' => true]);

        Log::shouldHaveReceived('warning')->once()->withArgs(
            fn (string $message) => str_contains($message, '[github]')
                && str_contains($message, '[verified]')
                && str_contains($message, 'emailVerifiedUsing')
                && ! str_contains($message, 'owner@example.com')
        );
    }

    public function test_a_refusal_without_such_a_claim_does_not_warn(): void
    {
        Log::spy();

        $this->refusedWith([]);

        Log::shouldNotHaveReceived('warning');
    }

    public function test_a_refusal_because_the_provider_says_unverified_does_not_warn(): void
    {
        Log::spy();

        $this->refusedWith(['email_verified' => false]);

        Log::shouldNotHaveReceived('warning');
    }

    #[WithFortresses('denyingCollisions')]
    public function test_it_denies_when_the_email_already_belongs_to_another_account(): void
    {
        $this->createUser(['email' => 'existing@example.com']);

        $this->mockSocialiteUser('github', ['id' => 'gh-5', 'email' => 'existing@example.com']);

        $this->expectException(OAuthException::class);
        $this->expectExceptionMessage(OAuthException::emailAlreadyExists()->getMessage());

        app(OAuthCallback::class)('github');
    }

    #[WithFortresses('requiringManualLinks')]
    public function test_it_asks_for_a_manual_link_even_when_the_email_is_verified(): void
    {
        $this->createUser(['email' => 'existing@example.com']);

        $this->mockSocialiteUser('github', ['id' => 'gh-8', 'email' => 'existing@example.com', 'email_verified' => true]);

        try {
            app(OAuthCallback::class)('github');
            $this->fail('The account was linked.');
        } catch (OAuthException $exception) {
            $this->assertSame(OAuthException::manualLinkRequired()->getMessage(), $exception->getMessage());
            $this->assertGuest();
        }
    }

    #[WithFortresses('withoutAutoLinking')]
    public function test_a_verified_email_is_not_linked_when_auto_linking_is_off(): void
    {
        // Linking by e-mail switched off: a verified e-mail is not enough.
        Log::spy();

        $this->refusedWith(['email_verified' => true]);

        Log::shouldNotHaveReceived('warning');
    }

    public function test_a_provider_user_without_raw_claims_is_not_trusted_and_not_warned_about(): void
    {
        Log::spy();
        $this->createUser(['email' => 'owner@example.com']);

        $this->mockContractOnlySocialiteUser('github', 'gh-1', 'owner@example.com');

        try {
            app(OAuthCallback::class)('github');
            $this->fail('The account was linked.');
        } catch (OAuthException) {
            $this->assertGuest();
            Log::shouldNotHaveReceived('warning');
        }
    }
}
