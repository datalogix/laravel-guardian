<?php

namespace Datalogix\Guardian\Tests\Feature;

use Datalogix\Guardian\Enums\Framework;
use Datalogix\Guardian\Enums\IdentifierKey;
use Datalogix\Guardian\Exceptions\FortressIdException;
use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Framework\Livewire\Layout;
use Datalogix\Guardian\Tests\Fixtures\User;
use Datalogix\Guardian\Tests\TestCase;
use InvalidArgumentException;

class FortressTest extends TestCase
{
    public function test_id_can_only_be_set_once(): void
    {
        $fortress = Fortress::make()->id('one');

        $this->expectException(FortressIdException::class);
        $this->expectExceptionMessage(FortressIdException::alreadySet('one', 'two')->getMessage());

        $fortress->id('two');
    }

    public function test_an_id_can_be_given_before_a_preset(): void
    {
        $fortress = Fortress::make()->id('panel')->basic();

        $this->assertSame('panel', $fortress->getId());
        $this->assertFalse($fortress->isDefault());
    }

    public function test_an_id_given_after_a_preset_replaces_the_one_of_the_preset(): void
    {
        $fortress = Fortress::make()->basic()->id('panel');

        $this->assertSame('panel', $fortress->getId());
        // The default fortress is the one that ends up with the "default" ID.
        $this->assertFalse($fortress->isDefault());
    }

    public function test_an_id_before_a_preset_keeps_the_rest_of_the_preset(): void
    {
        $fortress = Fortress::make()->id('panel')->admin();

        $this->assertSame('panel', $fortress->getId());
        $this->assertSame('admin', $fortress->getPath());
    }

    public function test_the_presets_give_their_ids(): void
    {
        $this->assertSame('default', Fortress::make()->basic()->getId());
        $this->assertTrue(Fortress::make()->basic()->isDefault());
        $this->assertSame('admin', Fortress::make()->admin()->getId());
        $this->assertSame('product', Fortress::make()->product()->getId());
        $this->assertSame('customer', Fortress::make()->basic('customer')->getId());
    }

    public function test_two_ids_of_its_own_conflict_even_through_a_preset(): void
    {
        $this->expectException(FortressIdException::class);
        $this->expectExceptionMessage(FortressIdException::alreadySet('panel', 'customer')->getMessage());

        Fortress::make()->id('panel')->basic('customer');
    }

    public function test_id_cannot_exceed_max_length(): void
    {
        $this->expectException(FortressIdException::class);
        $this->expectExceptionMessage(FortressIdException::tooLong(str_repeat('a', 21), 20)->getMessage());

        Fortress::make()->id(str_repeat('a', 21));
    }

    public function test_get_id_throws_when_not_set(): void
    {
        $this->expectException(FortressIdException::class);
        $this->expectExceptionMessage(FortressIdException::missing()->getMessage());

        Fortress::make()->getId();
    }

    public function test_default_flag(): void
    {
        $fortress = Fortress::make();

        $this->assertFalse($fortress->isDefault());

        $fortress->default();

        $this->assertTrue($fortress->isDefault());

        $fortress->default(false);

        $this->assertFalse($fortress->isDefault());
    }

    public function test_default_accepts_a_closure(): void
    {
        $fortress = Fortress::make()->default(fn () => true);

        $this->assertTrue($fortress->isDefault());
    }

    public function test_guard_defaults_to_web(): void
    {
        $this->assertSame('web', Fortress::make()->getGuard());
    }

    public function test_guard_can_be_customized(): void
    {
        $fortress = Fortress::make()->guard('admin');

        $this->assertSame('admin', $fortress->getGuard());
    }

    public function test_guard_name_cannot_exceed_max_length(): void
    {
        $this->expectException(FortressIdException::class);
        $this->expectExceptionMessage(FortressIdException::guardTooLong(str_repeat('a', 21), 20)->getMessage());

        Fortress::make()->guard(str_repeat('a', 21));
    }

    public function test_can_access_allows_users_without_the_fortress_user_contract(): void
    {
        $fortress = Fortress::make();
        $user = new class extends \Illuminate\Foundation\Auth\User {};

        $this->assertTrue($fortress->canAccess($user));
        $this->assertFalse($fortress->cannotAccess($user));
    }

    public function test_can_access_defers_to_the_fortress_user_contract(): void
    {
        $fortress = Fortress::make();

        $allowed = $this->createUser(['can_access' => true]);
        $denied = $this->createUser(['can_access' => false]);

        $this->assertTrue($fortress->canAccess($allowed));
        $this->assertFalse($fortress->canAccess($denied));
        $this->assertTrue($fortress->cannotAccess($denied));
    }

    public function test_auth_model_class_resolves_the_configured_provider_model(): void
    {
        $this->assertSame(User::class, Fortress::make()->authModelClass());
    }

    public function test_identifier_key_defaults_to_email(): void
    {
        $this->assertSame(IdentifierKey::Email, Fortress::make()->getIdentifierKey());
    }

    public function test_identifier_key_can_be_set_from_the_enum_or_a_string(): void
    {
        $this->assertSame(IdentifierKey::CPF, Fortress::make()->identifierKey(IdentifierKey::CPF)->getIdentifierKey());
        $this->assertSame(IdentifierKey::Username, Fortress::make()->identifierKey('username')->getIdentifierKey());
    }

    public function test_framework_defaults_to_the_config_value(): void
    {
        config(['guardian.framework' => Framework::Inertia]);
        $this->assertSame(Framework::Inertia, Fortress::make()->getFramework());

        config(['guardian.framework' => Framework::Livewire]);
        $this->assertSame(Framework::Livewire, Fortress::make()->getFramework());
    }

    public function test_framework_can_be_forced_to_livewire_or_inertia(): void
    {
        $this->assertSame(Framework::Inertia, Fortress::make()->inertia()->getFramework());
        $this->assertSame(Framework::Livewire, Fortress::make()->livewire()->getFramework());
    }

    public function test_layout_is_unset_by_default(): void
    {
        $this->assertNull(Fortress::make()->getLayout());
    }

    public function test_layout_can_be_overridden_globally_and_per_page(): void
    {
        $fortress = Fortress::make()->layout(Layout::Split);

        $this->assertSame(Layout::Split->value, $fortress->getLayout());
        $this->assertSame(Layout::Split->value, $fortress->getLayoutForPage('login'));

        $fortress->layoutForPage('login', Layout::Simple);

        $this->assertSame(Layout::Simple->value, $fortress->getLayoutForPage('login'));
        $this->assertSame(Layout::Split->value, $fortress->getLayoutForPage('sign-up'));
    }

    public function test_database_transactions_are_enabled_by_default(): void
    {
        $this->assertTrue(Fortress::make()->hasDatabaseTransactions());
    }

    public function test_database_transactions_can_be_disabled(): void
    {
        $this->assertFalse(Fortress::make()->databaseTransactions(false)->hasDatabaseTransactions());
    }

    public function test_wrap_in_database_transaction_executes_the_callback(): void
    {
        $result = Fortress::make()->wrapInDatabaseTransaction(fn () => 'value');

        $this->assertSame('value', $result);
    }

    public function test_wrap_in_database_transaction_runs_outside_a_transaction_when_disabled(): void
    {
        $fortress = Fortress::make()->databaseTransactions(false);

        $result = $fortress->wrapInDatabaseTransaction(fn () => 'value');

        $this->assertSame('value', $result);
    }

    public function test_basic_mode_configures_the_core_authentication_features(): void
    {
        $fortress = Fortress::make()->basic();

        $this->assertSame('default', $fortress->getId());
        $this->assertTrue($fortress->isDefault());
        $this->assertTrue($fortress->getLoginFeature()->hasFeature());
        $this->assertTrue($fortress->getLogoutFeature()->hasFeature());
        $this->assertTrue($fortress->getForgotPasswordFeature()->hasFeature());
        $this->assertTrue($fortress->getResetPasswordFeature()->hasFeature());
        $this->assertTrue($fortress->getPasswordConfirmationFeature()->hasFeature());
        $this->assertFalse($fortress->getSignUpFeature()->hasFeature());
    }

    public function test_basic_mode_accepts_a_custom_id_and_is_not_default_when_renamed(): void
    {
        $fortress = Fortress::make()->basic('secondary');

        $this->assertSame('secondary', $fortress->getId());
        $this->assertFalse($fortress->isDefault());
    }

    public function test_admin_mode_extends_basic_with_an_admin_path(): void
    {
        $fortress = Fortress::make()->admin();

        $this->assertSame('admin', $fortress->getId());
        $this->assertSame('admin', $fortress->getPath());
        $this->assertTrue($fortress->getLoginFeature()->hasFeature());
    }

    public function test_product_mode_adds_sign_up_and_email_verification(): void
    {
        $fortress = Fortress::make()->product();

        $this->assertTrue($fortress->getSignUpFeature()->hasFeature());
        $this->assertTrue($fortress->isEmailVerificationRequired());
    }

    public function test_path_and_domain_configuration(): void
    {
        $fortress = Fortress::make()->path('shop')->domain('shop.test');

        $this->assertSame('shop', $fortress->getPath());
        $this->assertSame(['shop.test'], $fortress->getDomains());
    }

    public function test_domain_null_clears_domains(): void
    {
        $fortress = Fortress::make()->domain('shop.test')->domain(null);

        $this->assertSame([], $fortress->getDomains());
    }

    public function test_generate_route_name_prefixes_non_default_fortresses(): void
    {
        $default = Fortress::make()->id('default')->default();
        $secondary = Fortress::make()->id('secondary');

        $this->assertSame('auth.login', $default->generateRouteName('auth.login'));
        $this->assertSame('guardian.secondary.auth.login', $secondary->generateRouteName('auth.login'));
    }

    public function test_oauth_provider_names_cannot_exceed_the_column_length(): void
    {
        $provider = str_repeat('a', Fortress::MAX_OAUTH_PROVIDER_LENGTH + 1);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("OAuth provider [{$provider}] is longer than");

        Fortress::make()->oauth(providers: [$provider]);
    }

    public function test_oauth_provider_names_up_to_the_column_length_are_accepted(): void
    {
        $provider = str_repeat('a', Fortress::MAX_OAUTH_PROVIDER_LENGTH);

        $this->assertSame([$provider], Fortress::make()->oauth(providers: [$provider])->getOAuthProviders());
    }
}
