<?php

namespace Datalogix\Guardian\Support;

class RecoveryCodes
{
    /**
     * @return array<int, string>
     */
    public function generate(int $total = 8): array
    {
        $codes = [];

        for ($i = 0; $i < $total; $i++) {
            $codes[] = strtolower(bin2hex(random_bytes(5)));
        }

        return $codes;
    }
}
