<?php

namespace Datalogix\Guardian\Actions\Concerns;

use Illuminate\Support\Facades\Schema;

trait HasEmailVerifiedColumn
{
    protected static array $modelColumnCache = [];

    protected function hasEmailVerifiedColumn(string $modelClass): bool
    {
        return $this->hasModelColumn($modelClass, 'email_verified_at');
    }

    protected function hasModelColumn(string $modelClass, string $column): bool
    {
        $cacheKey = $modelClass.'|'.$column;

        if (array_key_exists($cacheKey, static::$modelColumnCache)) {
            return static::$modelColumnCache[$cacheKey];
        }

        $model = new $modelClass;

        return static::$modelColumnCache[$cacheKey] = Schema::connection($model->getConnectionName())
            ->hasColumn($model->getTable(), $column);
    }
}
