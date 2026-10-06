<?php

namespace Datalogix\Guardian\Tests\Feature;

use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

class MigrationsTest extends TestCase
{
    protected const TWO_FACTOR_COLUMNS = ['two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at'];

    protected function migrate(string $direction): void
    {
        $paths = $this->packageMigrationPaths();

        foreach ($direction === 'down' ? array_reverse($paths) : $paths as $path) {
            (require $path)->{$direction}();
        }
    }

    protected function assertMigrated(bool $migrated): void
    {
        foreach (static::TWO_FACTOR_COLUMNS as $column) {
            $this->assertSame($migrated, Schema::hasColumn('users', $column), "users.{$column}");
        }

        $this->assertSame($migrated, Schema::hasTable('two_factor_trusted_devices'));
        $this->assertSame($migrated, Schema::hasTable('oauth_identities'));
    }

    public function test_rolling_back_removes_what_they_added_and_nothing_else(): void
    {
        $this->assertMigrated(true);

        $this->migrate('down');

        $this->assertMigrated(false);
        $this->assertTrue(Schema::hasColumns('users', ['id', 'name', 'email', 'password']));
    }

    public function test_they_run_again_after_a_rollback(): void
    {
        $this->migrate('down');
        $this->migrate('up');

        $this->assertMigrated(true);
        $this->createUser(['two_factor_secret' => 'secret']);
    }

    public function test_running_them_twice_changes_nothing(): void
    {
        $this->migrate('up');

        $this->assertMigrated(true);
    }

    public static function tables(): array
    {
        return [
            'trusted devices' => ['two_factor_trusted_devices', 1],
            'oauth identities' => ['oauth_identities', 2],
        ];
    }

    #[DataProvider('tables')]
    public function test_a_table_of_the_application_with_the_same_name_is_not_taken_over(string $table, int $migration): void
    {
        Schema::drop($table);
        Schema::create($table, fn ($blueprint) => $blueprint->id());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("[{$table}] table already exists but is missing columns Guardian expects.");

        (require $this->packageMigrationPaths()[$migration])->up();
    }
}
