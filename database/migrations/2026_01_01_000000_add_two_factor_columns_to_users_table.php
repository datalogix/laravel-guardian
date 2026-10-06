<?php

use Datalogix\Guardian\Contracts\CanManageTwoFactorAuthentication;
use Datalogix\Guardian\Contracts\CanManageTwoFactorRecoveryCodes;
use Datalogix\Guardian\Contracts\TwoFactorAuthenticatable;
use Datalogix\Guardian\Contracts\TwoFactorRecoveryCodeAuthenticatable;
use Datalogix\Guardian\Exceptions\UnsupportedAuthGuardException;
use Datalogix\Guardian\FortressRegistry;
use Datalogix\Guardian\Support\TwoFactor\TwoFactorUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        [$secret, $recoveryCodes, $confirmedAt] = $this->columns();

        $tables = $this->tables();

        // Fail on a missing table, or the migration would be marked as run without any column.
        // Not when pretending: no query runs, so every table looks missing.
        foreach ($tables as [$connection, $tableName, $source]) {
            $schema = Schema::connection($connection);

            if (! $schema->getConnection()->pretending() && ! $schema->hasTable($tableName)) {
                throw new RuntimeException(
                    "The [{$tableName}] table of {$source} does not exist. ".
                    'Create it before running this migration, or set guardian.tables.users. '.
                    'When a migration of the application creates it after this one runs, publish '.
                    "Guardian's migrations with the guardian-migrations tag so they run after it."
                );
            }
        }

        foreach ($tables as [$connection, $tableName, , $model]) {
            $schema = Schema::connection($connection);

            // A model that reads and saves them itself, through the contracts, keeps them elsewhere.
            $columns = array_filter([
                $secret => ! $this->storesItself($model, TwoFactorAuthenticatable::class, CanManageTwoFactorAuthentication::class),
                $recoveryCodes => ! $this->storesItself($model, TwoFactorRecoveryCodeAuthenticatable::class, CanManageTwoFactorRecoveryCodes::class),
                $confirmedAt => true,
            ]);

            $schema->table($tableName, function (Blueprint $table) use ($schema, $tableName, $columns, $confirmedAt) {
                $after = $schema->hasColumn($tableName, 'password') ? 'password' : null;

                foreach (array_keys($columns) as $name) {
                    if (! $schema->hasColumn($tableName, $name)) {
                        $column = $name === $confirmedAt ? $table->timestamp($name)->nullable() : $table->text($name)->nullable();

                        if ($after !== null) {
                            $column->after($after);
                        }
                    }

                    $after = $name;
                }
            });
        }
    }

    public function down(): void
    {
        [$secret, $recoveryCodes, $confirmedAt] = $this->columns();

        foreach ($this->tables() as [$connection, $tableName, , $model]) {
            $schema = Schema::connection($connection);

            if (! $schema->hasTable($tableName)) {
                continue;
            }

            // Only the columns up() added.
            $columns = array_keys(array_filter([
                $secret => ! $this->storesItself($model, TwoFactorAuthenticatable::class, CanManageTwoFactorAuthentication::class),
                $recoveryCodes => ! $this->storesItself($model, TwoFactorRecoveryCodeAuthenticatable::class, CanManageTwoFactorRecoveryCodes::class),
                $confirmedAt => true,
            ]));

            $schema->table($tableName, function (Blueprint $table) use ($schema, $tableName, $columns) {
                $existing = array_filter($columns, fn (string $column): bool => $schema->hasColumn($tableName, $column));

                if ($existing !== []) {
                    $table->dropColumn($existing);
                }
            });
        }
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    protected function columns(): array
    {
        $twoFactorUser = app(TwoFactorUser::class);

        return [
            $twoFactorUser->getSecretColumn(),
            $twoFactorUser->getRecoveryCodesColumn(),
            $twoFactorUser->getConfirmedAtColumn(),
        ];
    }

    protected function storesItself(?string $model, string $readContract, string $saveContract): bool
    {
        return $model !== null && is_a($model, $readContract, true) && is_a($model, $saveContract, true);
    }

    /**
     * @return array<int, array{0: string|null, 1: string, 2: string, 3: class-string|null}>
     */
    protected function tables(): array
    {
        $tables = [];

        foreach ($this->entries() as [$entry, $source]) {
            if (is_a($entry, Model::class, true)) {
                $model = new $entry;
                $table = [$model->getConnectionName(), $model->getTable(), $source, $entry];
            } else {
                $table = [null, $entry, $source, null];
            }

            $tables[($table[0] ?? '').'|'.$table[1]] ??= $table;
        }

        return array_values($tables);
    }

    /**
     * @return array<int, array{0: string, 1: string}>
     */
    protected function entries(): array
    {
        $configured = config('guardian.tables.users');

        if ($configured !== null) {
            return array_map(fn (string $entry): array => [$entry, 'guardian.tables.users'], (array) $configured);
        }

        $entries = [];

        foreach (app(FortressRegistry::class)->all() as $fortress) {
            if (! $fortress->hasAnyTwoFactorFeature()) {
                continue;
            }

            try {
                $model = $fortress->authModelClass();
            } catch (UnsupportedAuthGuardException) {
                // A guard without an Eloquent provider has no model to read the table from.
                continue;
            }

            if (is_a($model, Model::class, true)) {
                $entries[] = [$model, "the model [{$model}] of the [{$fortress->getId()}] fortress"];
            }
        }

        return $entries !== [] ? $entries : [['users', 'the default users table']];
    }
};
