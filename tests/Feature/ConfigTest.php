<?php

namespace Datalogix\Guardian\Tests\Feature;

use Datalogix\Guardian\Enums\Framework;
use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\FortressRegistry;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\GuardianServiceProvider;
use Datalogix\Guardian\Support\OAuth\OAuthIdentities;
use Datalogix\Guardian\Support\TwoFactor\Totp;
use Datalogix\Guardian\Support\TwoFactor\TrustedDevices;
use Datalogix\Guardian\Support\TwoFactor\TwoFactorChallengeVerifier;
use Datalogix\Guardian\Support\TwoFactor\TwoFactorUser;
use Datalogix\Guardian\Tests\Fixtures\Admin;
use Datalogix\Guardian\Tests\Fixtures\OtherConnectionAdmin;
use Datalogix\Guardian\Tests\Fixtures\SecretManagingAdmin;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use PragmaRX\Google2FA\Google2FA;
use RuntimeException;

class ConfigTest extends TestCase
{
    public function test_the_config_file_loads_without_deprecations_when_no_framework_is_set(): void
    {
        $previous = getenv('GUARDIAN_FRAMEWORK');
        putenv('GUARDIAN_FRAMEWORK');
        unset($_ENV['GUARDIAN_FRAMEWORK'], $_SERVER['GUARDIAN_FRAMEWORK']);

        $deprecations = [];
        set_error_handler(function (int $errno, string $message) use (&$deprecations) {
            $deprecations[] = $message;

            return true;
        }, E_DEPRECATED | E_USER_DEPRECATED);

        try {
            $config = require dirname(__DIR__, 2).'/config/guardian.php';
        } finally {
            restore_error_handler();

            if ($previous !== false) {
                putenv("GUARDIAN_FRAMEWORK={$previous}");
            }
        }

        $this->assertSame([], $deprecations);
        $this->assertSame(Framework::Livewire, $config['framework']);
    }

    public function test_the_tables_can_be_renamed(): void
    {
        // An application has one set of tables, and index names are database-wide.
        Schema::drop('two_factor_trusted_devices');
        Schema::drop('oauth_identities');

        config([
            'guardian.tables.two_factor_trusted_devices' => 'guardian_trusted_devices',
            'guardian.tables.oauth_identities' => 'guardian_oauth_identities',
        ]);

        [, $trustedDevices, $oauthIdentities] = $this->packageMigrationPaths();
        (require $trustedDevices)->up();
        (require $oauthIdentities)->up();

        $this->assertTrue(Schema::hasTable('guardian_trusted_devices'));
        $this->assertTrue(Schema::hasTable('guardian_oauth_identities'));

        $fortress = Guardian::getCurrentOrDefaultFortress();
        $user = $this->createUser();

        $issued = app(TrustedDevices::class)->issue($fortress, $user);
        app(OAuthIdentities::class)->link($fortress, $user, 'github', '123');

        $this->assertDatabaseHas('guardian_trusted_devices', ['id' => $issued['id']]);
        $this->assertDatabaseHas('guardian_oauth_identities', ['provider' => 'github', 'provider_user_id' => '123']);

        (require $oauthIdentities)->down();
        (require $trustedDevices)->down();

        $this->assertFalse(Schema::hasTable('guardian_trusted_devices'));
        $this->assertFalse(Schema::hasTable('guardian_oauth_identities'));
    }

    public function test_the_two_factor_columns_are_added_to_every_configured_users_table(): void
    {
        Schema::create('admins', function ($table) {
            $table->id();
            $table->string('email');
        });

        config(['guardian.tables.users' => ['users', 'admins']]);

        $migration = require $this->packageMigrationPaths()[0];
        $migration->up();

        $columns = ['two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at'];

        $this->assertTrue(Schema::hasColumns('admins', $columns));
        $this->assertTrue(Schema::hasColumns('users', $columns));

        $migration->down();

        foreach ($columns as $column) {
            $this->assertFalse(Schema::hasColumn('admins', $column), "admins.{$column}");
            $this->assertFalse(Schema::hasColumn('users', $column), "users.{$column}");
        }
    }

    public function test_a_missing_users_table_stops_the_migration_before_changing_any_table(): void
    {
        Schema::create('admins', function ($table) {
            $table->id();
        });

        config(['guardian.tables.users' => ['admins', 'missing_table']]);

        $migration = require $this->packageMigrationPaths()[0];

        try {
            $migration->up();
            $this->fail('The migration ran with a missing users table.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('[missing_table] table of guardian.tables.users does not exist', $exception->getMessage());
        }

        $this->assertFalse(Schema::hasColumn('admins', 'two_factor_secret'));

        // Rolling back skips the table that is not there.
        $migration->down();
    }

    public function test_a_pretended_migration_lists_the_changes_without_checking_the_tables(): void
    {
        $migration = require $this->packageMigrationPaths()[0];
        $migration->down();

        $queries = DB::connection()->pretend(fn () => $migration->up());

        $alters = array_filter(array_column($queries, 'query'), fn (string $query): bool => str_starts_with($query, 'alter table'));

        $this->assertStringContainsString('two_factor_secret', implode("\n", $alters));
        $this->assertFalse(Schema::hasColumn('users', 'two_factor_secret'));
    }

    public function test_a_users_model_class_puts_the_two_factor_columns_on_its_own_connection(): void
    {
        config(['database.connections.admins' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);

        Schema::connection('admins')->create('admins', function ($table) {
            $table->id();
        });

        // A table with the same name on the default connection must be left alone.
        Schema::create('admins', function ($table) {
            $table->id();
        });

        config(['guardian.tables.users' => ['users', OtherConnectionAdmin::class]]);

        $migration = require $this->packageMigrationPaths()[0];
        $migration->up();

        $columns = ['two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at'];

        $this->assertTrue(Schema::connection('admins')->hasColumns('admins', $columns));
        $this->assertFalse(Schema::hasColumn('admins', 'two_factor_secret'));

        $migration->down();

        $this->assertFalse(Schema::connection('admins')->hasColumn('admins', 'two_factor_secret'));
        $this->assertFalse(Schema::hasColumn('users', 'two_factor_secret'));
    }

    public function test_without_configured_users_tables_the_models_of_the_two_factor_fortresses_get_the_columns(): void
    {
        $this->app['config']->set('auth.providers.admins', ['driver' => 'eloquent', 'model' => Admin::class]);
        $this->app['config']->set('auth.guards.admin', ['driver' => 'session', 'provider' => 'admins']);

        Schema::create('admins', function ($table) {
            $table->id();
        });

        $registry = new FortressRegistry;
        $registry->register(Fortress::make()->basic()->twoFactor());
        $registry->register(Fortress::make()->admin()->guard('admin')->twoFactor());
        $this->app->instance(FortressRegistry::class, $registry);

        config(['guardian.tables.users' => null]);

        $migration = require $this->packageMigrationPaths()[0];
        $migration->down();
        $migration->up();

        $columns = ['two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at'];

        $this->assertTrue(Schema::hasColumns('users', $columns));
        $this->assertTrue(Schema::hasColumns('admins', $columns));

        $migration->down();

        $this->assertFalse(Schema::hasColumn('users', 'two_factor_secret'));
        $this->assertFalse(Schema::hasColumn('admins', 'two_factor_secret'));
    }

    public function test_without_configured_users_tables_a_fortress_without_two_factor_leaves_its_table_alone(): void
    {
        $this->app['config']->set('auth.providers.admins', ['driver' => 'eloquent', 'model' => Admin::class]);
        $this->app['config']->set('auth.guards.admin', ['driver' => 'session', 'provider' => 'admins']);

        Schema::create('admins', function ($table) {
            $table->id();
        });

        $registry = new FortressRegistry;
        $registry->register(Fortress::make()->basic()->twoFactor());
        $registry->register(Fortress::make()->admin()->guard('admin'));
        $this->app->instance(FortressRegistry::class, $registry);

        config(['guardian.tables.users' => null]);

        $migration = require $this->packageMigrationPaths()[0];
        $migration->down();
        $migration->up();

        $this->assertTrue(Schema::hasColumn('users', 'two_factor_secret'));
        $this->assertFalse(Schema::hasColumn('admins', 'two_factor_secret'));
    }

    public function test_without_configured_users_tables_or_an_eloquent_provider_the_users_table_gets_the_columns(): void
    {
        $this->app['config']->set('auth.providers.database_users', ['driver' => 'database', 'table' => 'users']);
        $this->app['config']->set('auth.guards.database', ['driver' => 'session', 'provider' => 'database_users']);

        $registry = new FortressRegistry;
        $registry->register(Fortress::make()->basic()->guard('database')->twoFactor());
        $this->app->instance(FortressRegistry::class, $registry);

        config(['guardian.tables.users' => null]);

        $migration = require $this->packageMigrationPaths()[0];
        $migration->down();
        $migration->up();

        $this->assertTrue(Schema::hasColumn('users', 'two_factor_secret'));
    }

    public function test_a_missing_table_of_a_fortress_model_names_the_fortress(): void
    {
        $this->app['config']->set('auth.providers.admins', ['driver' => 'eloquent', 'model' => Admin::class]);
        $this->app['config']->set('auth.guards.admin', ['driver' => 'session', 'provider' => 'admins']);

        $registry = new FortressRegistry;
        $registry->register(Fortress::make()->admin()->guard('admin')->twoFactor());
        $this->app->instance(FortressRegistry::class, $registry);

        config(['guardian.tables.users' => null]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The [admins] table of the model ['.Admin::class.'] of the [admin] fortress does not exist.');

        (require $this->packageMigrationPaths()[0])->up();
    }

    public function test_the_totp_issuer_can_be_configured(): void
    {
        config(['guardian.totp_issuer' => 'Acme Admin']);

        $uri = app(Totp::class)->makeOtpAuthUri(app(Totp::class)->generateSecret(), 'user@example.com');

        $this->assertStringContainsString(rawurlencode('Acme Admin'), $uri);
        $this->assertStringNotContainsString(rawurlencode(config('app.name')), $uri);
    }

    public function test_an_empty_totp_issuer_falls_back_to_the_app_name(): void
    {
        config(['guardian.totp_issuer' => '']);

        $uri = app(Totp::class)->makeOtpAuthUri(app(Totp::class)->generateSecret(), 'user@example.com');

        $this->assertStringContainsString(rawurlencode(config('app.name')), $uri);
    }

    public function test_the_security_counters_use_the_configured_cache_store(): void
    {
        config([
            'cache.stores.guardian' => ['driver' => 'array'],
            'guardian.cache_store' => 'guardian',
        ]);

        $fortress = Guardian::getCurrentOrDefaultFortress();
        $secret = app(Totp::class)->generateSecret();
        $user = $this->createUser();
        app(TwoFactorUser::class)->saveTwoFactorSecret($user, $fortress, $secret);

        $code = app(Google2FA::class)->getCurrentOtp($secret);

        $this->assertTrue(app(TwoFactorChallengeVerifier::class)->verify($user->fresh(), $fortress, $code)->isValid());

        $lastUsed = implode(':', ['guardian:two-factor:totp-last-used', $fortress->getId(), $user::class, $user->getKey()]);

        $this->assertNotNull(Cache::store('guardian')->get($lastUsed));
        $this->assertNull(Cache::store()->get($lastUsed));
    }

    public function test_guardians_tables_can_live_on_another_connection(): void
    {
        config([
            'database.connections.guardian' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'guardian.database_connection' => 'guardian',
        ]);

        [, $trustedDevices, $oauthIdentities] = $this->packageMigrationPaths();
        (require $trustedDevices)->up();
        (require $oauthIdentities)->up();

        $this->assertTrue(Schema::connection('guardian')->hasTable('two_factor_trusted_devices'));
        $this->assertTrue(Schema::connection('guardian')->hasTable('oauth_identities'));

        $fortress = Guardian::getCurrentOrDefaultFortress();
        $user = $this->createUser();

        $issued = app(TrustedDevices::class)->issue($fortress, $user);
        app(OAuthIdentities::class)->link($fortress, $user, 'github', '123');

        $this->assertSame(1, DB::connection('guardian')->table('two_factor_trusted_devices')->where('id', $issued['id'])->count());
        $this->assertSame(1, DB::connection('guardian')->table('oauth_identities')->where('provider_user_id', '123')->count());
        $this->assertDatabaseCount('two_factor_trusted_devices', 0);
        $this->assertDatabaseCount('oauth_identities', 0);

        (require $oauthIdentities)->down();
        (require $trustedDevices)->down();

        $this->assertFalse(Schema::connection('guardian')->hasTable('two_factor_trusted_devices'));
        $this->assertTrue(Schema::hasTable('two_factor_trusted_devices'));
    }

    public function test_the_migrations_can_be_published(): void
    {
        $paths = ServiceProvider::pathsToPublish(GuardianServiceProvider::class, 'guardian-migrations');

        $this->assertSame([realpath(dirname(__DIR__, 2).'/database/migrations')], array_map('realpath', array_keys($paths)));
        $this->assertSame([database_path('migrations')], array_values($paths));
    }

    public function test_the_two_factor_columns_can_be_renamed(): void
    {
        config(['guardian.columns' => [
            'secret' => 'guardian_two_factor_secret',
            'recovery_codes' => 'guardian_two_factor_recovery_codes',
            'confirmed_at' => 'guardian_two_factor_confirmed_at',
        ]]);

        $migration = require $this->packageMigrationPaths()[0];
        $migration->up();

        $columns = ['guardian_two_factor_secret', 'guardian_two_factor_recovery_codes', 'guardian_two_factor_confirmed_at'];

        $this->assertTrue(Schema::hasColumns('users', $columns));

        $fortress = Guardian::getCurrentOrDefaultFortress();
        $twoFactorUser = app(TwoFactorUser::class);
        $user = $this->createUser();

        $twoFactorUser->saveTwoFactorSecret($user, $fortress, 'a-secret');
        $twoFactorUser->saveTwoFactorRecoveryCodes($user, $fortress, ['recovery-code-1']);

        $row = DB::table('users')->find($user->getKey());

        $this->assertNotNull($row->guardian_two_factor_secret);
        $this->assertNotNull($row->guardian_two_factor_recovery_codes);
        $this->assertNotNull($row->guardian_two_factor_confirmed_at);
        $this->assertNull($row->two_factor_secret);
        $this->assertNull($row->two_factor_recovery_codes);

        $user = $user->fresh();

        $this->assertSame('a-secret', $twoFactorUser->getTwoFactorSecret($user, $fortress));
        $this->assertTrue($twoFactorUser->hasTwoFactorEnabled($user, $fortress));
        $this->assertTrue($twoFactorUser->consumeTwoFactorRecoveryCode($user, $fortress, 'recovery-code-1'));

        $migration->down();

        foreach ($columns as $column) {
            $this->assertFalse(Schema::hasColumn('users', $column), "users.{$column}");
        }

        $this->assertTrue(Schema::hasColumn('users', 'two_factor_secret'));
    }

    public function test_a_model_that_keeps_its_secret_itself_gets_no_secret_column(): void
    {
        Schema::create('secret_managing_admins', fn ($table) => $table->id());
        config(['guardian.tables.users' => [SecretManagingAdmin::class]]);

        $migration = require $this->packageMigrationPaths()[0];
        $migration->up();

        $this->assertFalse(Schema::hasColumn('secret_managing_admins', 'two_factor_secret'));
        $this->assertTrue(Schema::hasColumns('secret_managing_admins', ['two_factor_recovery_codes', 'two_factor_confirmed_at']));

        $migration->down();

        $this->assertSame(['id'], Schema::getColumnListing('secret_managing_admins'));
    }
}
