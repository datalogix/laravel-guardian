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
            return;
        }

        Schema::create('two_factor_trusted_devices', function (Blueprint $table) {
            $table->id();
            $table->string('fortress_id')->index();
            $table->string('auth_guard')->nullable()->index();
            $table->morphs('authenticatable');
            $table->string('name')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('revoked_at')->nullable()->index();
            $table->timestamps();

            $table->index(['fortress_id', 'auth_guard', 'authenticatable_type', 'authenticatable_id'], 'guardian_2fa_trusted_devices_lookup');
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
