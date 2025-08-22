<?php

namespace Datalogix\Guardian\Concerns;

use Closure;
use Illuminate\Support\Facades\DB;

trait HasDatabaseTransactions
{
    protected bool|Closure $hasDatabaseTransactions = false;

    public function databaseTransactions(bool|Closure $condition = true): static
    {
        $this->hasDatabaseTransactions = $condition;

        return $this;
    }

    public function hasDatabaseTransactions(): bool
    {
        return (bool) value($this->hasDatabaseTransactions);
    }

    public function beginDatabaseTransaction(): void
    {
        if ($this->hasDatabaseTransactions()) {
            DB::beginTransaction();
        }
    }

    public function commitDatabaseTransaction(): void
    {
        if ($this->hasDatabaseTransactions()) {
            DB::commit();
        }
    }

    public function rollBackDatabaseTransaction(): void
    {
        if ($this->hasDatabaseTransactions()) {
            DB::rollBack();
        }
    }

    public function wrapInDatabaseTransaction(Closure $callback): mixed
    {
        if (! $this->hasDatabaseTransactions()) {
            return $callback();
        }

        return DB::transaction($callback);
    }
}
