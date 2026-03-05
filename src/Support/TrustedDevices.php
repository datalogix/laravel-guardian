<?php

namespace Datalogix\Guardian\Support;

use Datalogix\Guardian\Fortress;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TrustedDevices
{
    protected string $table = 'two_factor_trusted_devices';

    public function isAvailable(): bool
    {
        return Schema::hasTable($this->table);
    }

    public function issue(Fortress $fortress, Model $user, int $days = 30, ?string $name = null): ?array
    {
        if (! $this->isAvailable()) {
            return null;
        }

        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $now = now();
        $expiresAt = now()->addDays(max(1, $days));

        $id = DB::table($this->table)->insertGetId([
            'fortress_id' => $fortress->getId(),
            'auth_guard' => $fortress->getGuard(),
            'authenticatable_type' => $user::class,
            'authenticatable_id' => (string) $user->getAuthIdentifier(),
            'name' => $name,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'token_hash' => $tokenHash,
            'last_used_at' => $now,
            'expires_at' => $expiresAt,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [
            'id' => (int) $id,
            'token' => $token,
            'expires_at' => $expiresAt,
        ];
    }

    public function touchIfValid(Fortress $fortress, Model $user, int $deviceId, string $token): bool
    {
        if (! $this->isAvailable()) {
            return false;
        }

        $record = DB::table($this->table)
            ->where('id', $deviceId)
            ->where('fortress_id', $fortress->getId())
            ->where('auth_guard', $fortress->getGuard())
            ->where('authenticatable_type', $user::class)
            ->where('authenticatable_id', (string) $user->getAuthIdentifier())
            ->whereNull('revoked_at')
            ->first();

        if (! $record) {
            return false;
        }

        if ($record->expires_at && now()->greaterThan($record->expires_at)) {
            $this->revoke((int) $record->id, $fortress, $user);

            return false;
        }

        if (! hash_equals((string) $record->token_hash, hash('sha256', $token))) {
            return false;
        }

        DB::table($this->table)
            ->where('id', $record->id)
            ->update([
                'last_used_at' => now(),
                'updated_at' => now(),
            ]);

        return true;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function list(Fortress $fortress, Model $user): array
    {
        if (! $this->isAvailable()) {
            return [];
        }

        return DB::table($this->table)
            ->where('fortress_id', $fortress->getId())
            ->where('auth_guard', $fortress->getGuard())
            ->where('authenticatable_type', $user::class)
            ->where('authenticatable_id', (string) $user->getAuthIdentifier())
            ->whereNull('revoked_at')
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->orderByDesc('last_used_at')
            ->get(['id', 'name', 'ip_address', 'user_agent', 'last_used_at', 'expires_at', 'created_at'])
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    public function revoke(int $deviceId, Fortress $fortress, Model $user): bool
    {
        if (! $this->isAvailable()) {
            return false;
        }

        return DB::table($this->table)
            ->where('id', $deviceId)
            ->where('fortress_id', $fortress->getId())
            ->where('auth_guard', $fortress->getGuard())
            ->where('authenticatable_type', $user::class)
            ->where('authenticatable_id', (string) $user->getAuthIdentifier())
            ->whereNull('revoked_at')
            ->update([
                'revoked_at' => now(),
                'updated_at' => now(),
            ]) > 0;
    }

    public function revokeAll(Fortress $fortress, Model $user): int
    {
        if (! $this->isAvailable()) {
            return 0;
        }

        return DB::table($this->table)
            ->where('fortress_id', $fortress->getId())
            ->where('auth_guard', $fortress->getGuard())
            ->where('authenticatable_type', $user::class)
            ->where('authenticatable_id', (string) $user->getAuthIdentifier())
            ->whereNull('revoked_at')
            ->update([
                'revoked_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function prune(int $retentionDays = 30): int
    {
        if (! $this->isAvailable()) {
            return 0;
        }

        $retentionDays = max(0, $retentionDays);
        $cutoff = now()->subDays($retentionDays);

        return DB::table($this->table)
            ->where(function ($query) use ($cutoff) {
                $query->where(function ($expired) {
                    $expired->whereNotNull('expires_at')
                        ->where('expires_at', '<=', now());
                })->orWhere(function ($revoked) use ($cutoff) {
                    $revoked->whereNotNull('revoked_at')
                        ->where('revoked_at', '<=', $cutoff);
                });
            })
            ->delete();
    }
}
