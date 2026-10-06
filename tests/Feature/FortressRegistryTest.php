<?php

namespace Datalogix\Guardian\Tests\Feature;

use Closure;
use Datalogix\Guardian\Enums\IdentifierKey;
use Datalogix\Guardian\Exceptions\EmailVerificationConfigurationException;
use Datalogix\Guardian\Exceptions\FortressIdException;
use Datalogix\Guardian\Exceptions\FrameworkConfigurationException;
use Datalogix\Guardian\Exceptions\IdentifierColumnConfigurationException;
use Datalogix\Guardian\Exceptions\MultipleDefaultFortressesException;
use Datalogix\Guardian\Exceptions\NoDefaultFortressSetException;
use Datalogix\Guardian\Exceptions\NoFortressRegisteredException;
use Datalogix\Guardian\Exceptions\OAuthConfigurationException;
use Datalogix\Guardian\Exceptions\OAuthProviderNotConfiguredException;
use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\FortressRegistry;
use Datalogix\Guardian\Framework\FrameworkResolver;
use Datalogix\Guardian\Tests\Fixtures\Adapters\UninstalledInertiaAdapter;
use Datalogix\Guardian\Tests\Fixtures\Adapters\UninstalledLivewireAdapter;
use Datalogix\Guardian\Tests\Fixtures\UserWithoutEmailVerification;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

class FortressRegistryTest extends TestCase
{
    public function test_it_throws_when_registering_a_duplicate_id(): void
    {
        $registry = new FortressRegistry;
        $registry->register(Fortress::make()->id('default')->default());

        $this->expectException(FortressIdException::class);
        $this->expectExceptionMessage(FortressIdException::duplicateInRegistry('default')->getMessage());

        $registry->register(Fortress::make()->id('default'));
    }

    public function test_get_default_throws_when_none_is_set(): void
    {
        $registry = new FortressRegistry;
        $registry->register(Fortress::make()->id('default'));

        $this->expectException(NoDefaultFortressSetException::class);
        $this->expectExceptionMessage(NoDefaultFortressSetException::make()->getMessage());

        $registry->getDefault();
    }

    public function test_get_default_returns_the_default_fortress(): void
    {
        $registry = new FortressRegistry;
        $registry->register(Fortress::make()->id('default')->default());

        $this->assertSame('default', $registry->getDefault()->getId());
    }

    public function test_the_default_fortress_is_current_again_on_the_manager_of_the_next_request(): void
    {
        $fortress = Fortress::make()->basic('next-request')->default();
        (new FortressRegistry)->register($fortress);

        // "guardian" is scoped: Octane and queue workers build a new manager for every request.
        $this->app->forgetScopedInstances();

        $this->assertSame($fortress, app('guardian')->getCurrentFortress());
    }

    public function test_get_falls_back_to_the_default_when_no_id_given(): void
    {
        $registry = new FortressRegistry;
        $registry->register(Fortress::make()->id('default')->default());

        $this->assertSame('default', $registry->get()->getId());
    }

    public function test_get_finds_by_strict_id(): void
    {
        $registry = new FortressRegistry;
        $registry->register(Fortress::make()->id('default')->default());
        $registry->register(Fortress::make()->id('admin'));

        $this->assertSame('admin', $registry->get('admin')->getId());
    }

    public function test_get_falls_back_to_default_when_id_not_found_and_strict(): void
    {
        $registry = new FortressRegistry;
        $registry->register(Fortress::make()->id('default')->default());

        $this->assertSame('default', $registry->get('missing')->getId());
    }

    public function test_get_can_find_by_normalized_id_when_not_strict(): void
    {
        $registry = new FortressRegistry;
        $registry->register(Fortress::make()->id('default')->default());
        $registry->register(Fortress::make()->id('my-admin'));

        $this->assertSame('my-admin', $registry->get('MyAdmin', isStrict: false)->getId());
        $this->assertSame('my-admin', $registry->get('my_admin', isStrict: false)->getId());
    }

    public function test_all_returns_every_registered_fortress(): void
    {
        $registry = new FortressRegistry;
        $registry->register(Fortress::make()->id('default')->default());
        $registry->register(Fortress::make()->id('admin'));

        $this->assertCount(2, $registry->all());
    }

    public function test_reset_clears_the_registry(): void
    {
        $registry = new FortressRegistry;
        $registry->register(Fortress::make()->id('default')->default());

        $registry->reset();

        $this->assertSame([], $registry->all());
    }

    public function test_validate_throws_when_no_fortress_is_registered(): void
    {
        $registry = new FortressRegistry;

        $this->expectException(NoFortressRegisteredException::class);
        $this->expectExceptionMessage(NoFortressRegisteredException::make()->getMessage());

        $registry->validate();
    }

    public function test_validate_throws_when_no_default_fortress_is_set(): void
    {
        $registry = new FortressRegistry;
        $registry->register(Fortress::make()->id('default'));

        $this->expectException(NoDefaultFortressSetException::class);
        $this->expectExceptionMessage(NoDefaultFortressSetException::make()->getMessage());

        $registry->validate();
    }

    public function test_validate_throws_when_multiple_defaults_are_set(): void
    {
        $registry = new FortressRegistry;
        $registry->register(Fortress::make()->id('one')->default());
        $registry->register(Fortress::make()->id('two')->default());

        $this->expectException(MultipleDefaultFortressesException::class);
        $this->expectExceptionMessage(MultipleDefaultFortressesException::make()->getMessage());

        $registry->validate();
    }

    public function test_validate_throws_when_email_verification_is_required_without_a_prompt_route(): void
    {
        $registry = new FortressRegistry;
        $registry->register(
            Fortress::make()->id('default')->default()
                ->emailVerification(promptRouteAction: false, isRequired: true)
        );

        $this->expectException(EmailVerificationConfigurationException::class);
        $this->expectExceptionMessage(EmailVerificationConfigurationException::missingPromptRoute('default')->getMessage());

        $registry->validate();
    }

    public function test_validate_throws_when_email_verification_is_required_without_a_verify_route(): void
    {
        $registry = new FortressRegistry;
        $registry->register(
            Fortress::make()->id('default')->default()
                ->emailVerification(verifyRouteAction: false, isRequired: true)
        );

        $this->expectException(EmailVerificationConfigurationException::class);
        $this->expectExceptionMessage(EmailVerificationConfigurationException::missingVerifyRoute('default')->getMessage());

        $registry->validate();
    }

    public function test_validate_passes_when_email_verification_is_fully_configured(): void
    {
        $this->expectNotToPerformAssertions();

        $registry = new FortressRegistry;
        $registry->register(
            Fortress::make()->id('default')->default()->emailVerification(isRequired: true)
        );

        $registry->validate();
    }

    public function test_validate_throws_when_oauth_is_enabled_without_socialite_installed(): void
    {
        $registry = $this->registryWithoutSocialite();
        $registry->register(
            Fortress::make()->id('default')->default()->oauth(providers: ['github'])
        );

        $this->expectException(OAuthConfigurationException::class);
        $this->expectExceptionMessage(OAuthConfigurationException::socialiteNotInstalled('default')->getMessage());

        $registry->validate();
    }

    public function test_validate_does_not_require_socialite_when_oauth_is_disabled(): void
    {
        $this->expectNotToPerformAssertions();

        $registry = $this->registryWithoutSocialite();
        $registry->register(Fortress::make()->id('default')->default());

        $registry->validate();
    }

    #[Group('socialite')]
    public function test_validate_throws_when_an_oauth_provider_has_no_credentials(): void
    {
        $registry = new FortressRegistry;
        $registry->register(
            Fortress::make()->id('default')->default()->oauth(providers: ['unconfigured-provider'])
        );

        $this->expectException(OAuthProviderNotConfiguredException::class);
        $this->expectExceptionMessage(OAuthProviderNotConfiguredException::make('default', 'unconfigured-provider')->getMessage());

        $registry->validate();
    }

    #[Group('socialite')]
    public function test_validate_passes_when_the_oauth_provider_is_configured(): void
    {
        $this->expectNotToPerformAssertions();

        $registry = new FortressRegistry;
        $registry->register(
            Fortress::make()->id('default')->default()->emailVerification(isRequired: false)->oauth(providers: ['github'])
        );

        $registry->validate();
    }

    public function test_validate_throws_when_the_identifier_column_is_missing(): void
    {
        $this->app['config']->set('auth.providers.ghost_users', [
            'driver' => 'eloquent',
            'model' => GhostUser::class,
        ]);
        $this->app['config']->set('auth.guards.ghost', [
            'driver' => 'session',
            'provider' => 'ghost_users',
        ]);

        Schema::create('ghost_users', fn ($table) => $table->id());

        $registry = new FortressRegistry;
        $registry->register(
            Fortress::make()->id('default')->default()->guard('ghost')->identifierKey(IdentifierKey::CPF)
        );

        $this->expectException(IdentifierColumnConfigurationException::class);
        $this->expectExceptionMessage(IdentifierColumnConfigurationException::missingColumn('default', GhostUser::class, 'cpf')->getMessage());

        $registry->validate();
    }

    public function test_validate_throws_when_sign_up_is_enabled_without_an_email_column(): void
    {
        $this->app['config']->set('auth.providers.ghost_users', [
            'driver' => 'eloquent',
            'model' => GhostUser::class,
        ]);
        $this->app['config']->set('auth.guards.ghost', [
            'driver' => 'session',
            'provider' => 'ghost_users',
        ]);

        Schema::create('ghost_users', fn ($table) => $table->id());

        $registry = new FortressRegistry;
        $registry->register(
            Fortress::make()->id('default')->default()
                ->guard('ghost')
                ->identifierKey(IdentifierKey::Login)
                ->signUp()
        );

        $this->expectException(IdentifierColumnConfigurationException::class);
        $this->expectExceptionMessage(IdentifierColumnConfigurationException::missingColumn('default', GhostUser::class, 'login')->getMessage());

        $registry->validate();
    }

    protected function registryWithGhostUsers(): FortressRegistry
    {
        $this->app['config']->set('auth.providers.ghost_users', ['driver' => 'eloquent', 'model' => GhostUser::class]);
        $this->app['config']->set('auth.guards.ghost', ['driver' => 'session', 'provider' => 'ghost_users']);

        $registry = new FortressRegistry;
        $registry->register(Fortress::make()->id('default')->default()->guard('ghost')->identifierKey(IdentifierKey::CPF));

        return $registry;
    }

    public function test_validate_passes_before_the_table_of_the_users_is_migrated(): void
    {
        // A fresh install boots the application to run `migrate`, before the table exists.
        $this->registryWithGhostUsers()->validate();

        $this->assertFalse(Schema::hasTable('ghost_users'));
    }

    public function test_the_columns_are_not_checked_on_web_requests(): void
    {
        Schema::create('ghost_users', fn ($table) => $table->id());
        $registry = $this->registryWithGhostUsers();

        (fn () => $this->isRunningInConsole = false)->call($this->app);

        try {
            $registry->validate();
        } finally {
            (fn () => $this->isRunningInConsole = true)->call($this->app);
        }

        $this->expectException(IdentifierColumnConfigurationException::class);

        $registry->validate();
    }

    public static function featuresThatSendEmail(): array
    {
        return [
            'sign-up' => [fn (Fortress $fortress) => $fortress->signUp()],
            'forgot password' => [fn (Fortress $fortress) => $fortress->passwordReset(resetPasswordRouteAction: false)],
            'reset password' => [fn (Fortress $fortress) => $fortress->passwordReset(forgotPasswordRouteAction: false)],
            'required e-mail verification' => [fn (Fortress $fortress) => $fortress->emailVerification(isRequired: true)],
        ];
    }

    /**
     * Has the login column but no email column.
     */
    protected function registryForALoginOnlyTable(Closure $configure): FortressRegistry
    {
        Schema::create('login_only_users', function ($table) {
            $table->id();
            $table->string('login')->unique();
            $table->string('password');
        });

        $this->app['config']->set('auth.providers.login_only_users', [
            'driver' => 'eloquent',
            'model' => LoginOnlyUser::class,
        ]);
        $this->app['config']->set('auth.guards.login-only', [
            'driver' => 'session',
            'provider' => 'login_only_users',
        ]);

        $registry = new FortressRegistry;
        $registry->register($configure(
            Fortress::make()->id('default')->default()->guard('login-only')->identifierKey(IdentifierKey::Login)->login()
        ));

        return $registry;
    }

    #[DataProvider('featuresThatSendEmail')]
    public function test_validate_throws_missing_email_column_for_a_feature_that_sends_email(Closure $feature): void
    {
        try {
            $this->registryForALoginOnlyTable($feature)->validate();
            $this->fail('Expected IdentifierColumnConfigurationException to be thrown.');
        } catch (IdentifierColumnConfigurationException $exception) {
            $this->assertStringContainsString('[email]', $exception->getMessage());
        } finally {
            Schema::dropIfExists('login_only_users');
        }
    }

    public function test_validate_does_not_need_an_email_column_when_no_feature_sends_email(): void
    {
        $this->expectNotToPerformAssertions();

        try {
            $this->registryForALoginOnlyTable(fn (Fortress $fortress) => $fortress)->validate();
        } finally {
            Schema::dropIfExists('login_only_users');
        }
    }

    public static function fortressesThatLaravelCannotSendTheVerificationEmailFor(): array
    {
        return [
            'sign-up' => [fn () => Fortress::make()->id('default')->default()->signUp()],
            'social login creating users' => [fn () => Fortress::make()->id('default')->default()->oauth(providers: ['github'])],
        ];
    }

    #[DataProvider('fortressesThatLaravelCannotSendTheVerificationEmailFor')]
    #[Group('socialite')]
    public function test_validate_throws_when_new_users_must_verify_their_email_without_a_verify_route(Closure $fortress): void
    {
        $registry = new FortressRegistry;
        $registry->register($fortress());

        $this->expectException(EmailVerificationConfigurationException::class);
        $this->expectExceptionMessage('MustVerifyEmail');

        $registry->validate();
    }

    public static function fortressesLaravelCanSendTheVerificationEmailFor(): array
    {
        return [
            'with a verify route' => [fn () => Fortress::make()->id('default')->default()->signUp()->emailVerification(isRequired: false)],
            'social login that creates no users' => [fn () => Fortress::make()->id('default')->default()->oauth(providers: ['github'], createUserIfMissing: false)],
            'no new users at all' => [fn () => Fortress::make()->id('default')->default()->login()],
        ];
    }

    #[DataProvider('fortressesLaravelCanSendTheVerificationEmailFor')]
    #[Group('socialite')]
    public function test_validate_passes_when_new_users_can_verify_their_email(Closure $fortress): void
    {
        $this->expectNotToPerformAssertions();

        $registry = new FortressRegistry;
        $registry->register($fortress());

        $registry->validate();
    }

    public function test_validate_passes_for_new_users_that_do_not_verify_their_email(): void
    {
        $this->expectNotToPerformAssertions();

        $this->app['config']->set('auth.providers.unverified_users', ['driver' => 'eloquent', 'model' => UserWithoutEmailVerification::class]);
        $this->app['config']->set('auth.guards.unverified', ['driver' => 'session', 'provider' => 'unverified_users']);

        $registry = new FortressRegistry;
        $registry->register(Fortress::make()->id('default')->default()->guard('unverified')->signUp());

        $registry->validate();
    }

    public function test_validate_leaves_the_verification_email_to_guardian_when_laravel_does_not_send_it(): void
    {
        // Guardian sends it only for a fortress with a verify route, so nothing can fail.
        $this->expectNotToPerformAssertions();

        Event::forget(Registered::class);

        $registry = new FortressRegistry;
        $registry->register(Fortress::make()->id('default')->default()->signUp());

        $registry->validate();
    }

    #[Group('inertia')]
    public function test_validate_passes_for_an_inertia_fortress(): void
    {
        $this->expectNotToPerformAssertions();

        $registry = new FortressRegistry;
        $registry->register(Fortress::make()->inertia()->basic());

        $registry->validate();
    }

    public function test_validate_throws_when_the_framework_package_is_not_installed(): void
    {
        app(FrameworkResolver::class)->register(new UninstalledLivewireAdapter);

        $registry = new FortressRegistry;
        $registry->register(Fortress::make()->livewire()->basic());

        $this->expectException(FrameworkConfigurationException::class);
        $this->expectExceptionMessage('livewire/livewire');

        $registry->validate();
    }

    public function test_validate_names_the_package_of_the_framework_in_use(): void
    {
        app(FrameworkResolver::class)->register(new UninstalledInertiaAdapter);

        $registry = new FortressRegistry;
        $registry->register(Fortress::make()->inertia()->basic());

        $this->expectException(FrameworkConfigurationException::class);
        $this->expectExceptionMessage('inertiajs/inertia-laravel');

        $registry->validate();
    }

    public function test_validate_ignores_a_missing_framework_package_when_every_feature_has_its_own_route_action(): void
    {
        $this->expectNotToPerformAssertions();

        app(FrameworkResolver::class)->register(new UninstalledLivewireAdapter);

        $registry = new FortressRegistry;
        $registry->register(
            Fortress::make()->id('default')->default()->livewire()->login(routeAction: fn () => 'my own login page')->logout()
        );

        $registry->validate();
    }

    public function test_validate_passes_for_a_fully_valid_configuration(): void
    {
        $this->expectNotToPerformAssertions();

        $registry = new FortressRegistry;
        $registry->register(Fortress::make()->basic());

        $registry->validate();
    }

    protected function registryWithoutSocialite(): FortressRegistry
    {
        return new class extends FortressRegistry
        {
            protected function socialiteIsInstalled(): bool
            {
                return false;
            }
        };
    }

    public function test_validate_passes_without_a_database_to_check(): void
    {
        // A build (`php artisan optimize` in a Docker image) boots without a database.
        $this->app['config']->set('database.connections.unreachable', [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => 1,
            'database' => 'nowhere',
            'username' => 'nobody',
            'password' => '',
        ]);
        $this->app['config']->set('auth.providers.unreachable_users', ['driver' => 'eloquent', 'model' => UnreachableUser::class]);
        $this->app['config']->set('auth.guards.unreachable', ['driver' => 'session', 'provider' => 'unreachable_users']);

        $registry = new FortressRegistry;
        $registry->register(Fortress::make()->id('default')->default()->guard('unreachable'));

        $registry->validate();

        $this->assertCount(1, $registry->all());
    }
}

class GhostUser extends Model
{
    protected $table = 'ghost_users';
}

class LoginOnlyUser extends Model
{
    protected $table = 'login_only_users';

    protected $guarded = [];
}

class UnreachableUser extends Model
{
    protected $connection = 'unreachable';

    protected $table = 'users';
}
