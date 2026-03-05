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
        if (Schema::hasTable('oauth_identities')) {
            return;
        }

        Schema::create('oauth_identities', function (Blueprint $table) {
            $table->id();
            $table->string('fortress_id')->index();
            $table->string('auth_guard')->nullable()->index();
            $table->morphs('authenticatable');
            $table->string('provider')->index();
            $table->string('provider_user_id');
            $table->string('email')->nullable()->index();
            $table->string('name')->nullable();
            $table->text('avatar')->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->unique(['fortress_id', 'auth_guard', 'provider', 'provider_user_id'], 'guardian_oauth_identity_unique_provider');
            $table->unique(['fortress_id', 'auth_guard', 'authenticatable_type', 'authenticatable_id', 'provider'], 'guardian_oauth_identity_unique_user_provider');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('oauth_identities');
    }
};
