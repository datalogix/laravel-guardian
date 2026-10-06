<?php

namespace Datalogix\Guardian\Tests\Feature\Concerns;

use Datalogix\Guardian\Actions\ForgotPassword;
use Datalogix\Guardian\Enums\IdentifierKey;
use Datalogix\Guardian\Exceptions\IdentifierValueMissingException;
use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\FortressRegistry;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Tests\TestCase;

class HasPasswordResetTest extends TestCase
{
    public function test_get_reset_password_url_signs_a_url_with_the_token_and_identifier(): void
    {
        $user = $this->createUser();

        $url = Guardian::getResetPasswordUrl('a-token', $user);

        $this->assertStringContainsString('a-token', $url);
        $this->assertStringContainsString(urlencode($user->email), $url);
        $this->assertStringContainsString('signature=', $url);
    }

    public function test_get_reset_password_url_uses_the_configured_identifier_column(): void
    {
        $this->fortressesOverride([Fortress::make()->basic()->identifierKey(IdentifierKey::Login)]);

        $user = $this->createUser(['login' => 'jdoe-account']);

        $url = Guardian::getResetPasswordUrl('a-token', $user);

        $this->assertStringContainsString('jdoe-account', $url);
    }

    protected function fortressesOverride(array $fortresses): void
    {
        // getEnvironmentSetUp already ran for the default single-fortress setup by
        // the time a test method executes, so this directly re-registers the
        // registry's content instead, which is enough for a plain URL builder.
        $registry = app(FortressRegistry::class);
        $registry->reset();

        foreach ($fortresses as $fortress) {
            $registry->register($fortress->default());
        }
    }

    public function test_get_reset_password_url_throws_when_the_identifier_value_is_missing(): void
    {
        $this->fortressesOverride([Fortress::make()->basic()->identifierKey(IdentifierKey::Login)]);

        $user = $this->createUser(['login' => null]);

        $this->expectException(IdentifierValueMissingException::class);
        $this->expectExceptionMessage(IdentifierValueMissingException::forUser('login', $user->getKey())->getMessage());

        Guardian::getResetPasswordUrl('a-token', $user);
    }

    public function test_password_reset_does_not_reveal_accounts_by_default(): void
    {
        $this->assertFalse(Fortress::make()->passwordReset()->passwordResetRevealsAccounts());
        $this->assertFalse(Fortress::make()->basic()->passwordResetRevealsAccounts());
    }

    public function test_a_fortress_can_choose_to_reveal_accounts(): void
    {
        $this->assertTrue(Fortress::make()->passwordReset(revealsAccounts: true)->passwordResetRevealsAccounts());
    }

    public function test_configuring_the_password_reset_again_goes_back_to_the_safe_default(): void
    {
        $fortress = Fortress::make()->passwordReset(revealsAccounts: true)->passwordReset();

        $this->assertFalse($fortress->passwordResetRevealsAccounts());
    }

    public function test_the_generic_answer_is_translated(): void
    {
        $this->assertSame(ForgotPassword::GENERIC_STATUS, __(ForgotPassword::GENERIC_STATUS));

        app()->setLocale('pt_BR');

        $this->assertSame(
            'Se existir uma conta para esse login, enviamos um link de redefinição de senha.',
            __(ForgotPassword::GENERIC_STATUS),
        );
    }
}
