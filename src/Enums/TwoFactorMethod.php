<?php

namespace Datalogix\Guardian\Enums;

enum TwoFactorMethod: string
{
    case Totp = 'totp';
    case Email = 'email';
    case Sms = 'sms';

    public function requiresDelivery(): bool
    {
        return $this !== self::Totp;
    }
}
