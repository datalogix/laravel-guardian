<?php

use Datalogix\Guardian\Support\OAuth\OAuthIdentities;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function getConnection(): ?string
    {
        return app(OAuthIdentities::class)->connectionName();
    }

    public function up(): void
    {
        $schema = Schema::connection($this->getConnection());
        $tableName = app(OAuthIdentities::class)->table();

        if ($schema->hasTable($tableName)) {
            $expected = [
                'fortress_id', 'auth_guard', 'authenticatable_type', 'authenticatable_id',
                'provider', 'provider_user_id', 'email', 'name', 'avatar',
                'access_token', 'refresh_token', 'token_expires_at', 'last_used_at',
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
            $table->morphs('authenticatable', 'guardian_oauth_authenticatable_idx');
            $table->string('provider', 50)->index();
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

    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists(app(OAuthIdentities::class)->table());
    }
};
