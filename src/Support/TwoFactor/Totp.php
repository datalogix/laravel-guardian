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
    ): bool {
        $normalizedCode = preg_replace('/\s+/', '', $code);

        if (! is_string($normalizedCode) || ! preg_match('/^\d{6}$/', $normalizedCode)) {
            return false;
        }

        return app(Google2FA::class)->verifyKey($secret, $normalizedCode, $window);
    }

    public function currentCode(string $secret): string
    {
        return str_pad((string) app(Google2FA::class)->getCurrentOtp($secret), 6, '0', STR_PAD_LEFT);
    }
}
