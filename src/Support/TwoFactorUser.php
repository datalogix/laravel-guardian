<?php

namespace Datalogix\Guardian\Support;

use Datalogix\Guardian\Contracts\CanManageTwoFactorAuthentication;
use Datalogix\Guardian\Contracts\CanManageTwoFactorRecoveryCodes;
use Datalogix\Guardian\Contracts\TwoFactorAuthenticatable;
use Datalogix\Guardian\Contracts\TwoFactorRecoveryCodeAuthenticatable;
use Datalogix\Guardian\Fortress;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class TwoFactorUser
{
    protected static array $columnCache = [];

    protected string $recoveryCodeHashPrefix = 'sha256:';

    public function hasTwoFactorEnabled(mixed $user, Fortress $fortress): bool
    {
        $secret = $this->getTwoFactorSecret($user, $fortress);

        if (! filled($secret)) {
            return false;
        }

        if (! $user instanceof Model || ! $this->hasConfirmedAtColumn($user)) {
            return true;
        }

        return $this->getTwoFactorConfirmedAt($user) !== null;
    }

    public function getTwoFactorConfirmedAt(mixed $user): ?Carbon
    {
        if (! $user instanceof Model || ! $this->hasConfirmedAtColumn($user)) {
            return null;
        }

        $value = $user->getAttribute($this->getConfirmedAtColumn());

        if ($value instanceof Carbon) {
            return $value;
        }

        if (is_string($value) && filled($value)) {
            return Carbon::parse($value);
        }

        return null;
    }

    public function getTwoFactorSecret(mixed $user, Fortress $fortress): ?string
    {
        if ($user instanceof TwoFactorAuthenticatable) {
            return $user->getTwoFactorSecret($fortress);
        }

        if (! $user instanceof Model || ! $this->hasSecretColumn($user)) {
            return null;
        }

        $value = $user->getAttribute($this->getSecretColumn());

        return is_string($value) && filled($value) ? $value : null;
    }

    public function canStoreTwoFactorSecret(mixed $user): bool
    {
        return $user instanceof CanManageTwoFactorAuthentication
            || ($user instanceof Model && $this->hasSecretColumn($user));
    }

    public function saveTwoFactorSecret(mixed $user, Fortress $fortress, ?string $secret): bool
    {
        if ($user instanceof CanManageTwoFactorAuthentication) {
            $user->saveTwoFactorSecret($fortress, $secret);

            $this->saveTwoFactorConfirmedAt($user, $secret !== null ? now() : null);

            return true;
        }

        if (! $user instanceof Model || ! $this->hasSecretColumn($user)) {
            return false;
        }

        $attributes = [$this->getSecretColumn() => $secret];

        if ($this->hasConfirmedAtColumn($user)) {
            $attributes[$this->getConfirmedAtColumn()] = $secret !== null ? now() : null;
        }

        $user->forceFill($attributes)->save();

        return true;
    }

    public function saveTwoFactorConfirmedAt(mixed $user, Carbon|string|int|null $confirmedAt): bool
    {
        if (! $user instanceof Model || ! $this->hasConfirmedAtColumn($user)) {
            return false;
        }

        $user->forceFill([$this->getConfirmedAtColumn() => $confirmedAt])->save();

        return true;
    }

    /**
     * @return array<int, string>
     */
    public function getTwoFactorRecoveryCodes(mixed $user, Fortress $fortress): array
    {
        if ($user instanceof TwoFactorRecoveryCodeAuthenticatable) {
            return array_values(array_filter($user->getTwoFactorRecoveryCodes($fortress), fn ($code) => is_string($code) && filled($code)));
        }

        if (! $user instanceof Model || ! $this->hasRecoveryCodesColumn($user)) {
            return [];
        }

        $value = $user->getAttribute($this->getRecoveryCodesColumn());

        if (is_array($value)) {
            return array_values(array_filter($value, fn ($code) => is_string($code) && filled($code) && ! $this->isHashedRecoveryCode($code)));
        }

        if (is_string($value) && filled($value)) {
            $decoded = json_decode($value, true);

            if (is_array($decoded)) {
                return array_values(array_filter($decoded, fn ($code) => is_string($code) && filled($code) && ! $this->isHashedRecoveryCode($code)));
            }
        }

        return [];
    }

    public function canStoreTwoFactorRecoveryCodes(mixed $user): bool
    {
        return $user instanceof CanManageTwoFactorRecoveryCodes
            || ($user instanceof Model && $this->hasRecoveryCodesColumn($user));
    }

    /**
     * @param  array<int, string>  $codes
     */
    public function saveTwoFactorRecoveryCodes(mixed $user, Fortress $fortress, array $codes): bool
    {
        if ($user instanceof CanManageTwoFactorRecoveryCodes) {
            $user->saveTwoFactorRecoveryCodes($fortress, $codes);

            return true;
        }

        if (! $user instanceof Model || ! $this->hasRecoveryCodesColumn($user)) {
            return false;
        }

        $hashedCodes = array_map(fn (string $code) => $this->hashRecoveryCode($code), array_values($codes));

        $user->forceFill([
            $this->getRecoveryCodesColumn() => json_encode($hashedCodes),
        ])->save();

        return true;
    }

    public function getTwoFactorRecoveryCodesCount(mixed $user, Fortress $fortress): int
    {
        return count($this->getStoredTwoFactorRecoveryCodes($user, $fortress));
    }

    public function consumeTwoFactorRecoveryCode(mixed $user, Fortress $fortress, string $candidate): bool
    {
        if (! $this->canStoreTwoFactorRecoveryCodes($user)) {
            return false;
        }

        $available = $this->getStoredTwoFactorRecoveryCodes($user, $fortress);
        $normalizedCandidate = $this->normalizeRecoveryCode($candidate);

        if (blank($normalizedCandidate)) {
            return false;
        }

        $remaining = [];
        $consumed = false;

        foreach ($available as $code) {
            if (! is_string($code) || blank($code)) {
                continue;
            }

            if (! $consumed && $this->isRecoveryCodeMatch($code, $normalizedCandidate)) {
                $consumed = true;

                continue;
            }

            $remaining[] = $code;
        }

        if (! $consumed) {
            return false;
        }

        if ($user instanceof CanManageTwoFactorRecoveryCodes) {
            $user->saveTwoFactorRecoveryCodes($fortress, $remaining);

            return true;
        }

        if (! $user instanceof Model || ! $this->hasRecoveryCodesColumn($user)) {
            return false;
        }

        $user->forceFill([
            $this->getRecoveryCodesColumn() => json_encode(array_values($remaining)),
        ])->save();

        return true;
    }

    protected function hasSecretColumn(Model $user): bool
    {
        return $this->hasColumn($user, $this->getSecretColumn());
    }

    protected function hasRecoveryCodesColumn(Model $user): bool
    {
        return $this->hasColumn($user, $this->getRecoveryCodesColumn());
    }

    protected function hasConfirmedAtColumn(Model $user): bool
    {
        return $this->hasColumn($user, $this->getConfirmedAtColumn());
    }

    protected function hasColumn(Model $user, string $column): bool
    {
        $cacheKey = $user::class.'|'.$user->getConnectionName().'|'.$user->getTable().'|'.$column;

        if (array_key_exists($cacheKey, self::$columnCache)) {
            return self::$columnCache[$cacheKey];
        }

        try {
            return self::$columnCache[$cacheKey] = Schema::connection($user->getConnectionName())->hasColumn($user->getTable(), $column);
        } catch (\Throwable) {
            return self::$columnCache[$cacheKey] = false;
        }
    }

    protected function getSecretColumn(): string
    {
        return 'two_factor_secret';
    }

    protected function getRecoveryCodesColumn(): string
    {
        return 'two_factor_recovery_codes';
    }

    protected function getConfirmedAtColumn(): string
    {
        return 'two_factor_confirmed_at';
    }

    /**
     * @return array<int, string>
     */
    protected function getStoredTwoFactorRecoveryCodes(mixed $user, Fortress $fortress): array
    {
        if ($user instanceof TwoFactorRecoveryCodeAuthenticatable) {
            return array_values(array_filter($user->getTwoFactorRecoveryCodes($fortress), fn ($code) => is_string($code) && filled($code)));
        }

        if (! $user instanceof Model || ! $this->hasRecoveryCodesColumn($user)) {
            return [];
        }

        $value = $user->getAttribute($this->getRecoveryCodesColumn());

        if (is_array($value)) {
            return array_values(array_filter($value, fn ($code) => is_string($code) && filled($code)));
        }

        if (is_string($value) && filled($value)) {
            $decoded = json_decode($value, true);

            if (is_array($decoded)) {
                return array_values(array_filter($decoded, fn ($code) => is_string($code) && filled($code)));
            }
        }

        return [];
    }

    protected function normalizeRecoveryCode(string $code): string
    {
        return strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $code));
    }

    protected function hashRecoveryCode(string $code): string
    {
        return $this->recoveryCodeHashPrefix.hash('sha256', $this->normalizeRecoveryCode($code));
    }

    protected function isHashedRecoveryCode(string $code): bool
    {
        return str_starts_with($code, $this->recoveryCodeHashPrefix);
    }

    protected function isRecoveryCodeMatch(string $storedCode, string $normalizedCandidate): bool
    {
        if ($this->isHashedRecoveryCode($storedCode)) {
            return hash_equals($storedCode, $this->hashRecoveryCode($normalizedCandidate));
        }

        return hash_equals($this->normalizeRecoveryCode($storedCode), $normalizedCandidate);
    }
}
