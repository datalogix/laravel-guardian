<?php

namespace Datalogix\Guardian\Support\TwoFactor;

class RecoveryCodes
{
    public function generate(int $total = 8): array
    {
        $codes = [];

        for ($i = 0; $i < $total; $i++) {
            $codes[] = strtolower(bin2hex(random_bytes(12)));
        }

        return $codes;
    }
}
