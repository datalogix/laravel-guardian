<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('two_factor_trusted_devices')) {
            $expected = [
                'fortress_id', 'auth_guard', 'authenticatable_type', 'authenticatable_id',
                'name', 'ip_address', 'user_agent', 'token_hash', 'last_used_at',
                'expires_at', 'revoked_at',
            ];

            if (! Schema::hasColumns('two_factor_trusted_devices', $expected)) {
                throw new RuntimeException(
                    'A [two_factor_trusted_devices] table already exists but is missing columns Guardian expects. '.
                    'Rename or drop the pre-existing table so this migration can create its own.'
                );
            }

            return;
        }

        Schema::create('two_factor_trusted_devices', function (Blueprint $table) {
            $table->id();
            $table->string('fortress_id', 20);
            $table->string('auth_guard', 20)->nullable()->index();
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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('two_factor_trusted_devices');
    }
};
