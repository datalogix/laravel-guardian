<?php

namespace Datalogix\Guardian\Support\TwoFactor;

use Datalogix\Guardian\Contracts\CanManageTwoFactorAuthentication;
use Datalogix\Guardian\Contracts\CanManageTwoFactorRecoveryCodes;
use Datalogix\Guardian\Contracts\TwoFactorAuthenticatable;
use Datalogix\Guardian\Contracts\TwoFactorRecoveryCodeAuthenticatable;
use Datalogix\Guardian\Enums\TwoFactorMethod;
use Datalogix\Guardian\Exceptions\TwoFactorSecretDecryptionException;
use Datalogix\Guardian\Fortress;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TwoFactorUser
{
    protected static array $columnCache = [];

    protected array $resolvedSecretCache = [];

    protected array $rawRecoveryCodesCache = [];

    protected string $recoveryCodeHashPrefix = 'sha256:';

    protected string $secretMethodSeparator = ':';

    public function hasTwoFactorEnabled(mixed $user, Fortress $fortress): bool
    {
        if ($user instanceof TwoFactorAuthenticatable) {
            return $user->hasTwoFactorEnabled($fortress);
        }

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
        return $this->resolveStoredSecret($user, $fortress)['secret'];
    }

    public function getTwoFactorMethod(mixed $user, Fortress $fortress): TwoFactorMethod
    {
        return $this->resolveStoredSecret($user, $fortress)['method'];
    }

    protected function resolveStoredSecret(mixed $user, Fortress $fortress): array
    {
        if (! is_object($user)) {
            return $this->buildStoredSecretParts($this->getStoredTwoFactorSecret($user, $fortress));
        }

        $cacheKey = $this->secretCacheKey($user, $fortress);

        return $this->resolvedSecretCache[$cacheKey] ??= $this->buildStoredSecretParts(
            $this->getStoredTwoFactorSecret($user, $fortress)
        );
    }

    protected function secretCacheKey(object $user, Fortress $fortress): string
    {
        if ($user instanceof Model) {
            return $user::class.'|'.$user->getKey().'|'.$fortress->getId();
        }

        return spl_object_id($user).'|'.$fortress->getId();
    }

    protected function buildStoredSecretParts(?string $stored): array
    {
        if (! is_string($stored) || blank($stored)) {
            return ['method' => TwoFactorMethod::Totp, 'secret' => null];
        }

        return $this->parseStoredSecret($stored);
    }

    protected function getStoredTwoFactorSecret(mixed $user, Fortress $fortress): ?string
    {
        if ($user instanceof TwoFactorAuthenticatable) {
            return $user->getTwoFactorSecret($fortress);
        }

        if (! $user instanceof Model || ! $this->hasSecretColumn($user)) {
            return null;
        }

        $value = $user->getAttribute($this->getSecretColumn());

        if (! is_string($value) || blank($value)) {
            return null;
        }

        return $this->decryptSecret($value);
    }

    protected function decryptSecret(string $value): ?string
    {
        try {
            return Crypt::decryptString($value);
        } catch (DecryptException $exception) {
            report($exception);

            throw new TwoFactorSecretDecryptionException(
                'Unable to decrypt the stored two-factor secret.', previous: $exception,
            );
        }
    }

    protected function parseStoredSecret(string $stored): array
    {
        $parts = explode($this->secretMethodSeparator, $stored, 2);

        if (count($parts) === 2) {
            [$method, $secret] = $parts;
            $resolvedMethod = TwoFactorMethod::tryFrom($method);

            if ($resolvedMethod instanceof TwoFactorMethod && filled($secret)) {
                return ['method' => $resolvedMethod, 'secret' => $secret];
            }
        }

        return ['method' => TwoFactorMethod::Totp, 'secret' => $stored];
    }

    public function canStoreTwoFactorSecret(mixed $user): bool
    {
        return $this->canUseContractOrColumn($user, CanManageTwoFactorAuthentication::class, $this->getSecretColumn());
    }

    public function saveTwoFactorSecret(mixed $user, Fortress $fortress, ?string $secret): bool
    {
        if ($user instanceof CanManageTwoFactorAuthentication) {
            $user->saveTwoFactorSecret($fortress, $secret);

            $this->saveTwoFactorConfirmedAt($user, $secret !== null ? now() : null);

            $this->forgetResolvedSecretCache($user, $fortress);

            return true;
        }

        if (! $user instanceof Model || ! $this->hasSecretColumn($user)) {
            return false;
        }

        $attributes = [$this->getSecretColumn() => $secret !== null ? Crypt::encryptString($secret) : null];

        if ($this->hasConfirmedAtColumn($user)) {
            $attributes[$this->getConfirmedAtColumn()] = $secret !== null ? now() : null;
        }

        $user->forceFill($attributes)->save();

        $this->forgetResolvedSecretCache($user, $fortress);

        return true;
    }

    protected function forgetResolvedSecretCache(mixed $user, Fortress $fortress): void
    {
        if (! is_object($user)) {
            return;
        }

        unset($this->resolvedSecretCache[$this->secretCacheKey($user, $fortress)]);
    }

    public function saveTwoFactorConfirmedAt(mixed $user, Carbon|string|int|null $confirmedAt): bool
    {
        if (! $user instanceof Model || ! $this->hasConfirmedAtColumn($user)) {
            return false;
        }

        $user->forceFill([$this->getConfirmedAtColumn() => $confirmedAt])->save();

        return true;
    }

    public function getTwoFactorRecoveryCodes(mixed $user, Fortress $fortress): array
    {
        return $this->filterRecoveryCodes($this->rawStoredRecoveryCodes($user, $fortress), excludeHashed: true);
    }

    public function canStoreTwoFactorRecoveryCodes(mixed $user): bool
    {
        return $this->canUseContractOrColumn($user, CanManageTwoFactorRecoveryCodes::class, $this->getRecoveryCodesColumn());
    }

    public function saveTwoFactorRecoveryCodes(mixed $user, Fortress $fortress, array $codes): bool
    {
        return $this->persistTwoFactorRecoveryCodes($user, $fortress, $codes, alreadyHashed: false);
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

        $normalizedCandidate = $this->normalizeRecoveryCode($candidate);

        if (blank($normalizedCandidate)) {
            return false;
        }

        if (
            $user instanceof Model
            && ! $user instanceof CanManageTwoFactorRecoveryCodes
            && $this->hasRecoveryCodesColumn($user)
        ) {
            return $this->consumeStoredRecoveryCodeWithLock($user, $fortress, $normalizedCandidate);
        }

        $remaining = $this->extractRemainingRecoveryCodes(
            $this->getStoredTwoFactorRecoveryCodes($user, $fortress),
            $normalizedCandidate,
        );

        if ($remaining === null) {
            return false;
        }

        return $this->persistTwoFactorRecoveryCodes($user, $fortress, $remaining, alreadyHashed: true);
    }

    protected function consumeStoredRecoveryCodeWithLock(Model $user, Fortress $fortress, string $normalizedCandidate): bool
    {
        return DB::transaction(function () use ($user, $fortress, $normalizedCandidate) {
            $locked = $user->newQuery()->lockForUpdate()->find($user->getKey());

            if (! $locked) {
                return false;
            }

            $available = $this->filterRecoveryCodes(
                $this->decodeStoredRecoveryCodesValue($locked->getAttribute($this->getRecoveryCodesColumn()))
            );

            $remaining = $this->extractRemainingRecoveryCodes($available, $normalizedCandidate);

            if ($remaining === null) {
                return false;
            }

            $locked->forceFill([
                $this->getRecoveryCodesColumn() => json_encode(array_values($remaining)),
            ])->save();

            $this->forgetRawRecoveryCodesCache($user, $fortress);

            return true;
        });
    }

    protected function extractRemainingRecoveryCodes(array $available, string $normalizedCandidate): ?array
    {
        $hashedCandidate = $this->hashRecoveryCode($normalizedCandidate);
        $remaining = [];
        $consumed = false;

        foreach ($available as $code) {
            if (! is_string($code) || blank($code)) {
                continue;
            }

            if (! $consumed && $this->isRecoveryCodeMatch($code, $normalizedCandidate, $hashedCandidate)) {
                $consumed = true;

                continue;
            }

            $remaining[] = $code;
        }

        return $consumed ? $remaining : null;
    }

    protected function persistTwoFactorRecoveryCodes(mixed $user, Fortress $fortress, array $codes, bool $alreadyHashed): bool
    {
        if ($user instanceof CanManageTwoFactorRecoveryCodes) {
            $user->saveTwoFactorRecoveryCodes($fortress, $codes);

            $this->forgetRawRecoveryCodesCache($user, $fortress);

            return true;
        }

        if (! $user instanceof Model || ! $this->hasRecoveryCodesColumn($user)) {
            return false;
        }

        $codes = $alreadyHashed
            ? array_values($codes)
            : array_map(fn (string $code) => $this->hashRecoveryCode($code), array_values($codes));

        $user->forceFill([
            $this->getRecoveryCodesColumn() => json_encode($codes),
        ])->save();

        $this->forgetRawRecoveryCodesCache($user, $fortress);

        return true;
    }

    protected function forgetRawRecoveryCodesCache(mixed $user, Fortress $fortress): void
    {
        if (! is_object($user)) {
            return;
        }

        unset($this->rawRecoveryCodesCache[$this->secretCacheKey($user, $fortress)]);
    }

    protected function canUseContractOrColumn(mixed $user, string $contract, string $column): bool
    {
        return $user instanceof $contract
            || ($user instanceof Model && $this->hasColumn($user, $column));
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
        } catch (\Throwable $exception) {
            report($exception);

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

    protected function getStoredTwoFactorRecoveryCodes(mixed $user, Fortress $fortress): array
    {
        return $this->filterRecoveryCodes($this->rawStoredRecoveryCodes($user, $fortress));
    }

    protected function rawStoredRecoveryCodes(mixed $user, Fortress $fortress): array
    {
        if (! is_object($user)) {
            return $this->resolveRawStoredRecoveryCodes($user, $fortress);
        }

        $cacheKey = $this->secretCacheKey($user, $fortress);

        return $this->rawRecoveryCodesCache[$cacheKey] ??= $this->resolveRawStoredRecoveryCodes($user, $fortress);
    }

    protected function resolveRawStoredRecoveryCodes(mixed $user, Fortress $fortress): array
    {
        if ($user instanceof TwoFactorRecoveryCodeAuthenticatable) {
            return $user->getTwoFactorRecoveryCodes($fortress);
        }

        if (! $user instanceof Model || ! $this->hasRecoveryCodesColumn($user)) {
            return [];
        }

        return $this->decodeStoredRecoveryCodesValue($user->getAttribute($this->getRecoveryCodesColumn()));
    }

    protected function decodeStoredRecoveryCodesValue(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && filled($value)) {
            $decoded = json_decode($value, true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    protected function filterRecoveryCodes(array $codes, bool $excludeHashed = false): array
    {
        return array_values(array_filter(
            $codes,
            fn ($code) => is_string($code) && filled($code) && (! $excludeHashed || ! $this->isHashedRecoveryCode($code)),
        ));
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

    protected function isRecoveryCodeMatch(string $storedCode, string $normalizedCandidate, string $hashedCandidate): bool
    {
        if ($this->isHashedRecoveryCode($storedCode)) {
            return hash_equals($storedCode, $hashedCandidate);
        }

        return hash_equals($this->normalizeRecoveryCode($storedCode), $normalizedCandidate);
    }
}
