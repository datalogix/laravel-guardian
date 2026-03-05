<?php

namespace Datalogix\Guardian\Support;

use Datalogix\Guardian\Fortress;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OAuthIdentities
{
    protected string $table = 'oauth_identities';

    public function isAvailable(): bool
    {
        return Schema::hasTable($this->table);
    }

    public function findAuthenticatableId(Fortress $fortress, string $provider, string $providerUserId): string|int|null
    {
        if (! $this->isAvailable()) {
            return null;
        }

        return DB::table($this->table)
            ->where('fortress_id', $fortress->getId())
            ->where('auth_guard', $fortress->getGuard())
            ->where('provider', $provider)
            ->where('provider_user_id', $providerUserId)
            ->value('authenticatable_id');
    }

    public function link(
        Fortress $fortress,
        Model $user,
        string $provider,
        string $providerUserId,
        ?string $email = null,
        ?string $name = null,
        ?string $avatar = null,
        ?string $accessToken = null,
        ?string $refreshToken = null,
        ?\DateTimeInterface $tokenExpiresAt = null,
    ): void {
        if (! $this->isAvailable()) {
            return;
        }

        $now = now();

        DB::table($this->table)->updateOrInsert(
            [
                'fortress_id' => $fortress->getId(),
                'auth_guard' => $fortress->getGuard(),
                'provider' => $provider,
                'provider_user_id' => $providerUserId,
            ],
            [
                'authenticatable_type' => $user::class,
                'authenticatable_id' => (string) $user->getAuthIdentifier(),
                'email' => $email,
                'name' => $name,
                'avatar' => $avatar,
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'token_expires_at' => $tokenExpiresAt,
                'last_used_at' => $now,
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );
    }
}
