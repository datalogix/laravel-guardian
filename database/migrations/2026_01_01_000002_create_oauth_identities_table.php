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
            $expected = [
                'fortress_id', 'auth_guard', 'authenticatable_type', 'authenticatable_id',
                'provider', 'provider_user_id', 'email', 'name', 'avatar',
                'access_token', 'refresh_token', 'token_expires_at', 'last_used_at',
            ];

            if (! Schema::hasColumns('oauth_identities', $expected)) {
                throw new RuntimeException(
                    'An [oauth_identities] table already exists but is missing columns Guardian expects. '.
                    'Rename or drop the pre-existing table so this migration can create its own.'
                );
            }

            return;
        }

        Schema::create('oauth_identities', function (Blueprint $table) {
            $table->id();
            $table->string('fortress_id', 20);
            $table->string('auth_guard', 20)->nullable()->index();
            $table->morphs('authenticatable', 'guardian_oauth_authenticatable_idx');
            $table->string('provider', 20)->index();
            $table->string('provider_user_id', 191);
            $table->string('email')->nullable()->index();
            $table->string('name')->nullable();
            $table->text('avatar')->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->unique(['fortress_id', 'auth_guard', 'provider', 'provider_user_id'], 'guardian_oauth_provider');
            $table->unique(['fortress_id', 'auth_guard', 'authenticatable_type', 'authenticatable_id', 'provider'], 'guardian_oauth_lookup');
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
