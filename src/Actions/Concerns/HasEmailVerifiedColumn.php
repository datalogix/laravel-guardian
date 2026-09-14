<?php

namespace Datalogix\Guardian\Actions\Concerns;

use Illuminate\Support\Facades\Schema;

trait HasEmailVerifiedColumn
{
    protected static array $emailVerifiedColumnCache = [];

    protected function hasEmailVerifiedColumn(string $modelClass): bool
    {
        if (array_key_exists($modelClass, static::$emailVerifiedColumnCache)) {
            return static::$emailVerifiedColumnCache[$modelClass];
        }

        return static::$emailVerifiedColumnCache[$modelClass] = Schema::hasColumn((new $modelClass)->getTable(), 'email_verified_at');
    }
}
