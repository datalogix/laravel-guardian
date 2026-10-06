<?php

namespace Datalogix\Guardian\Concerns;

use Closure;
use Illuminate\Support\Facades\DB;

trait HasDatabaseTransactions
{
    protected bool|Closure $hasDatabaseTransactions = true;

    public function databaseTransactions(bool|Closure $condition = true): static
    {
        $this->hasDatabaseTransactions = $condition;

        return $this;
    }

    public function hasDatabaseTransactions(): bool
    {
        return (bool) value($this->hasDatabaseTransactions);
    }

    public function wrapInDatabaseTransaction(Closure $callback): mixed
    {
        if (! $this->hasDatabaseTransactions()) {
            return $callback();
        }

        return DB::connection((new ($this->authModelClass()))->getConnectionName())->transaction($callback);
    }
}
