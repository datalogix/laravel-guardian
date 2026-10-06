<?php

namespace Datalogix\Guardian\Tests\Feature\Concerns;

use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Tests\Fixtures\OtherConnectionAdmin;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Support\Facades\DB;

class HasDatabaseTransactionsTest extends TestCase
{
    public function test_the_callback_runs_in_a_transaction(): void
    {
        $level = Fortress::make()->wrapInDatabaseTransaction(fn () => DB::transactionLevel());

        $this->assertSame(1, $level);
        $this->assertSame(0, DB::transactionLevel());
    }

    public function test_the_callback_runs_without_a_transaction_when_disabled(): void
    {
        $level = Fortress::make()->databaseTransactions(false)->wrapInDatabaseTransaction(fn () => DB::transactionLevel());

        $this->assertSame(0, $level);
    }

    public function test_the_transaction_is_on_the_connection_of_the_users(): void
    {
        config([
            'database.connections.admins' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'auth.providers.admins' => ['driver' => 'eloquent', 'model' => OtherConnectionAdmin::class],
            'auth.guards.admin' => ['driver' => 'session', 'provider' => 'admins'],
        ]);

        $levels = Fortress::make()->guard('admin')->wrapInDatabaseTransaction(fn () => [
            'admins' => DB::connection('admins')->transactionLevel(),
            'default' => DB::transactionLevel(),
        ]);

        $this->assertSame(['admins' => 1, 'default' => 0], $levels);
    }
}
