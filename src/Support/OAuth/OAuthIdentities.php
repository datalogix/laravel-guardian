<?php

namespace Datalogix\Guardian\Support\OAuth;

use Datalogix\Guardian\Exceptions\OAuthException;
use Datalogix\Guardian\Fortress;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OAuthIdentities
{
    protected string $table = 'oauth_identities';

    public function isAvailable(): bool
    {
        return Schema::hasTable($this->table);
    }

    public function findAuthenticatableId(Fortress $fortress, string $provider, string $providerUserId, string $authenticatableType): string|int|null
    {
        if (! $this->isAvailable()) {
            return null;
        }

        return DB::table($this->table)
            ->where('fortress_id', $fortress->getId())
            ->where('auth_guard', $fortress->getGuard())
            ->where('provider', $provider)
            ->where('provider_user_id', $providerUserId)
            ->where('authenticatable_type', $authenticatableType)
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

        $lookup = [
            'fortress_id' => $fortress->getId(),
            'auth_guard' => $fortress->getGuard(),
            'authenticatable_type' => $user::class,
            'authenticatable_id' => (string) $user->getAuthIdentifier(),
            'provider' => $provider,
        ];

        $existingProviderUserId = DB::table($this->table)->where($lookup)->value('provider_user_id');

        if ($existingProviderUserId !== null && (string) $existingProviderUserId !== $providerUserId) {
            throw OAuthException::identityAlreadyLinked();
        }

        $this->releaseStaleProviderIdentity($fortress, $user, $provider, $providerUserId);

        $now = now();

        DB::table($this->table)->updateOrInsert(
            $lookup,
            [
                'provider_user_id' => $providerUserId,
                'email' => $email,
                'name' => $name,
                'avatar' => $avatar,
                'access_token' => $accessToken !== null ? Crypt::encryptString($accessToken) : null,
                'refresh_token' => $refreshToken !== null ? Crypt::encryptString($refreshToken) : null,
                'token_expires_at' => $tokenExpiresAt,
                'last_used_at' => $now,
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );
    }

    protected function releaseStaleProviderIdentity(Fortress $fortress, Model $user, string $provider, string $providerUserId): void
    {
        $conflicting = DB::table($this->table)
            ->where('fortress_id', $fortress->getId())
            ->where('auth_guard', $fortress->getGuard())
            ->where('provider', $provider)
            ->where('provider_user_id', $providerUserId)
            ->where(function ($query) use ($user) {
                $query->where('authenticatable_type', '!=', $user::class)
                    ->orWhere('authenticatable_id', '!=', (string) $user->getAuthIdentifier());
            })
            ->first();

        if (! $conflicting) {
            return;
        }

        if ($this->authenticatableStillExists($conflicting->authenticatable_type, $conflicting->authenticatable_id)) {
            throw OAuthException::identityAlreadyLinked();
        }

        DB::table($this->table)->where('id', $conflicting->id)->delete();
    }

    protected function authenticatableStillExists(string $type, string|int $id): bool
    {
        if (! class_exists($type) || ! is_a($type, Model::class, true)) {
            return false;
        }

        return $type::query()->whereKey($id)->exists();
    }

    public function unlink(Fortress $fortress, Model $user, string $provider): bool
    {
        if (! $this->isAvailable()) {
            return false;
        }

        return DB::table($this->table)->where([
            'fortress_id' => $fortress->getId(),
            'auth_guard' => $fortress->getGuard(),
            'authenticatable_type' => $user::class,
            'authenticatable_id' => (string) $user->getAuthIdentifier(),
            'provider' => $provider,
        ])->delete() > 0;
    }

    public function find(Fortress $fortress, Model $user, string $provider): ?array
    {
        if (! $this->isAvailable()) {
            return null;
        }

        $row = DB::table($this->table)->where([
            'fortress_id' => $fortress->getId(),
            'auth_guard' => $fortress->getGuard(),
            'authenticatable_type' => $user::class,
            'authenticatable_id' => (string) $user->getAuthIdentifier(),
            'provider' => $provider,
        ])->first();

        return $row ? $this->hydrate($row) : null;
    }

    public function allFor(Fortress $fortress, Model $user): array
    {
        if (! $this->isAvailable()) {
            return [];
        }

        return DB::table($this->table)->where([
            'fortress_id' => $fortress->getId(),
            'auth_guard' => $fortress->getGuard(),
            'authenticatable_type' => $user::class,
            'authenticatable_id' => (string) $user->getAuthIdentifier(),
        ])->get()->map(fn ($row) => $this->hydrate($row))->all();
    }

    protected function hydrate(object $row): array
    {
        return [
            'provider' => $row->provider,
            'provider_user_id' => $row->provider_user_id,
            'email' => $row->email,
            'name' => $row->name,
            'avatar' => $row->avatar,
            'access_token' => $this->decrypt($row->access_token),
            'refresh_token' => $this->decrypt($row->refresh_token),
            'token_expires_at' => $row->token_expires_at,
            'last_used_at' => $row->last_used_at,
        ];
    }

    protected function decrypt(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException $exception) {
            report($exception);

            return null;
        }
    }
}
