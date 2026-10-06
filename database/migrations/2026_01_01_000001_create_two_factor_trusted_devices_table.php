<?php

use Datalogix\Guardian\Support\TwoFactor\TrustedDevices;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function getConnection(): ?string
    {
        return app(TrustedDevices::class)->connectionName();
    }

    public function up(): void
    {
        $schema = Schema::connection($this->getConnection());
        $tableName = app(TrustedDevices::class)->table();

        if ($schema->hasTable($tableName)) {
            $expected = [
                'fortress_id', 'auth_guard', 'authenticatable_type', 'authenticatable_id',
                'name', 'ip_address', 'user_agent', 'token_hash', 'last_used_at',
                'expires_at', 'revoked_at',
            ];

            if (! $schema->hasColumns($tableName, $expected)) {
                throw new RuntimeException(
                    "A [{$tableName}] table already exists but is missing columns Guardian expects. ".
                    'Rename or drop the pre-existing table so this migration can create its own.'
                );
            }

            return;
        }

        $schema->create($tableName, function (Blueprint $table) {
            $table->id();
            $table->string('fortress_id', 20);
            $table->string('auth_guard', 20)->index();
            $table->morphs('authenticatable', 'guardian_two_factor_authenticatable_idx');
            $table->string('name')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('revoked_at')->nullable()->index();
            $table->timestamps();

            $table->index(['fortress_id', 'auth_guard', 'authenticatable_type', 'authenticatable_id'], 'guardian_two_factor_lookup');
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists(app(TrustedDevices::class)->table());
    }
};
