<?php

namespace Datalogix\Guardian\Support\TwoFactor;

use Datalogix\Guardian\Enums\TwoFactorMethod;
use PragmaRX\Google2FA\Google2FA;

class Totp
{
    public function generateSecret(int $length = 32): string
    {
        return app(Google2FA::class)->generateSecretKey($length);
    }

    public function makeOtpAuthUri(string $secret, string $account, ?string $issuer = null): string
    {
        $issuer ??= (string) config('app.name', 'Laravel');

        return app(Google2FA::class)->getQRCodeUrl($issuer, $account, $secret);
    }

    public function verify(
        string $secret,
        string $code,
        int $window = 1,
        ?int $oldTimestamp = null,
    ): bool|int {
        $normalizedCode = preg_replace('/\s+/', '', $code);

        if (! is_string($normalizedCode) || ! preg_match('/^\d{6}$/', $normalizedCode)) {
            return false;
        }

        if ($oldTimestamp !== null) {
            return app(Google2FA::class)->verifyKeyNewer($secret, $normalizedCode, $oldTimestamp, $window);
        }

        return app(Google2FA::class)->verifyKey($secret, $normalizedCode, $window);
    }

    public function currentCode(string $secret): string
    {
        return str_pad((string) app(Google2FA::class)->getCurrentOtp($secret), 6, '0', STR_PAD_LEFT);
    }

    public function windowForTtl(int|false|null $ttl): int
    {
        if (! is_int($ttl) || $ttl <= 0) {
            return 20;
        }

        return max(1, (int) ceil($ttl / 30));
    }

    public function windowFor(TwoFactorMethod $method, int|false|null $ttl): int
    {
        return $method === TwoFactorMethod::Totp ? 1 : $this->windowForTtl($ttl);
    }

    public function resolveAccountLabel(mixed $user, string $default = 'user'): string
    {
        $account = $default;

        if (method_exists($user, 'getAuthIdentifier')) {
            $identifier = $user->getAuthIdentifier();

            if (is_scalar($identifier) && filled((string) $identifier)) {
                $account = (string) $identifier;
            }
        }

        if (method_exists($user, 'getEmailForVerification')) {
            $email = $user->getEmailForVerification();

            if (is_string($email) && filled($email)) {
                $account = $email;
            }
        }

        return $account;
    }
}
