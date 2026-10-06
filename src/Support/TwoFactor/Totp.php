<?php

namespace Datalogix\Guardian\Support\TwoFactor;

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

        if (! preg_match('/^\d{6}$/', $normalizedCode)) {
            return false;
        }

        return app(Google2FA::class)->verifyKeyNewer($secret, $normalizedCode, $oldTimestamp ?? 0, $window);
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
