<?php

namespace Datalogix\Guardian\Tests\Feature;

use Datalogix\Guardian\Enums\Framework;
use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\FortressRegistry;
use Datalogix\Guardian\Framework\FrameworkResolver;
use Datalogix\Guardian\Framework\Inertia\Controllers\LoginController as InertiaLoginController;
use Datalogix\Guardian\Framework\Livewire\Pages\Login;
use Datalogix\Guardian\GuardianManager;
use Datalogix\Guardian\GuardianServiceProvider;
use Datalogix\Guardian\Support\TwoFactor\TwoFactorUser;
use Datalogix\Guardian\Tests\Attributes\WithFortresses;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use PHPUnit\Framework\Attributes\Group;
use ReflectionMethod;

class GuardianServiceProviderTest extends TestCase
{
    public function test_guardian_is_bound_to_the_guardian_manager(): void
    {
        $this->assertInstanceOf(GuardianManager::class, $this->app->make('guardian'));
        $this->assertSame($this->app->make('guardian'), $this->app->make(GuardianManager::class));
    }

    public function test_guardian_manager_is_scoped(): void
    {
        $this->assertSame($this->app->make('guardian'), $this->app->make('guardian'));
    }

    public function test_fortress_registry_is_a_singleton(): void
    {
        $this->assertSame(
            $this->app->make(FortressRegistry::class),
            $this->app->make(FortressRegistry::class),
        );
    }

    public function test_two_factor_user_is_resolvable_and_scoped(): void
    {
        $this->assertInstanceOf(TwoFactorUser::class, $this->app->make(TwoFactorUser::class));
        $this->assertSame($this->app->make(TwoFactorUser::class), $this->app->make(TwoFactorUser::class));
    }

    #[Group('livewire')]
    #[Group('inertia')]
    public function test_framework_resolver_has_livewire_and_inertia_registered(): void
    {
        $resolver = $this->app->make(FrameworkResolver::class);

        $this->assertSame(
            Login::class,
            $resolver->resolveComponent('login', Framework::Livewire)
        );

        $this->assertSame(
            InertiaLoginController::class,
            $resolver->resolveComponent('login', Framework::Inertia)
        );
    }

    public function test_config_is_published(): void
    {
        $paths = ServiceProvider::pathsToPublish(GuardianServiceProvider::class, 'guardian-config');

        $this->assertNotEmpty($paths);
        $this->assertStringEndsWith('config/guardian.php', array_key_first($paths));
    }

    public function test_lang_is_published(): void
    {
        $paths = ServiceProvider::pathsToPublish(GuardianServiceProvider::class, 'guardian-lang');

        $this->assertNotEmpty($paths);
    }

    public function test_package_views_are_loaded(): void
    {
        $this->assertTrue(view()->exists('guardian::login'));
    }

    public function test_console_commands_are_registered(): void
    {
        $this->assertArrayHasKey('guardian:cache-components', $this->app['Illuminate\Contracts\Console\Kernel']->all());
        $this->assertArrayHasKey('guardian:clear-cached-components', $this->app['Illuminate\Contracts\Console\Kernel']->all());
        $this->assertArrayHasKey('guardian:prune-trusted-devices', $this->app['Illuminate\Contracts\Console\Kernel']->all());
    }

    public function test_login_route_is_registered_after_boot(): void
    {
        $this->assertTrue(Route::has('auth.login'));
    }

    protected function withOAuth(): array
    {
        return [Fortress::make()->basic()->emailVerification(isRequired: false)->oauth(providers: ['github'])];
    }

    #[Group('socialite')]
    #[WithFortresses('withOAuth')]
    public function test_oauth_migration_is_loaded(): void
    {
        $paths = implode('|', $this->app->make('migrator')->paths());

        $this->assertStringContainsString('create_oauth_identities_table', $paths);
        $this->assertStringNotContainsString('add_two_factor_columns_to_users_table', $paths);
    }

    public function test_trusted_device_pruning_scheduling_is_a_no_op_without_a_bound_schedule(): void
    {
        // Schedule is bound by a console provider, which a minimal container may not load.
        $container = new Container;
        $provider = new GuardianServiceProvider($container);

        $method = new ReflectionMethod($provider, 'scheduleTrustedDevicePruningConditionally');
        $method->setAccessible(true);

        $method->invoke($provider, [Fortress::make()->basic()->twoFactor(rememberOnDevice: true)]);

        $this->assertFalse($container->bound(Schedule::class));
    }

    protected function withTwoFactor(): array
    {
        return [Fortress::make()->basic()->twoFactor()];
    }

    #[WithFortresses('withTwoFactor')]
    public function test_two_factor_migration_is_loaded_but_not_trusted_devices_or_oauth(): void
    {
        $paths = implode('|', $this->app->make('migrator')->paths());

        $this->assertStringContainsString('add_two_factor_columns_to_users_table', $paths);
        $this->assertStringNotContainsString('create_two_factor_trusted_devices_table', $paths);
        $this->assertStringNotContainsString('create_oauth_identities_table', $paths);
    }
}
