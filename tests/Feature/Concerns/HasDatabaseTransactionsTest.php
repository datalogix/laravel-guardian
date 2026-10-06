<?php

namespace Datalogix\Guardian\Tests\Feature\Concerns;

use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Support\Facades\DB;

class HasDatabaseTransactionsTest extends TestCase
{
    public function test_begin_commit_and_roll_back_delegate_to_the_db_facade(): void
    {
        $fortress = Fortress::make();

        $fortress->beginDatabaseTransaction();
        $this->assertSame(1, DB::transactionLevel());

        $fortress->rollBackDatabaseTransaction();
        $this->assertSame(0, DB::transactionLevel());

        $fortress->beginDatabaseTransaction();
        $fortress->commitDatabaseTransaction();
        $this->assertSame(0, DB::transactionLevel());
    }

    public function test_begin_commit_and_roll_back_are_no_ops_when_disabled(): void
    {
        $fortress = Fortress::make()->databaseTransactions(false);

        $fortress->beginDatabaseTransaction();
        $fortress->commitDatabaseTransaction();
        $fortress->rollBackDatabaseTransaction();

        $this->assertSame(0, DB::transactionLevel());
    }
}
