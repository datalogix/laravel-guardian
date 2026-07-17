<?php

namespace Datalogix\Guardian\Support\TwoFactor;

class TwoFactorTotpVerifier
{
    public function verify(?string $secret, string $code): bool
    {
        return is_string($secret) && app(Totp::class)->verify($secret, $code);
    }
}
