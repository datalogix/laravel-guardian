<?php

namespace Datalogix\Guardian\Rules\Concerns;

trait ValidatesCheckDigit
{
    private function checkDigit(string $value, int $length, array $weights): int
    {
        $sum = 0;

        for ($i = 0; $i < $length; $i++) {
            $sum += (ord($value[$i]) - 48) * $weights[$i];
        }

        $remainder = $sum % 11;

        return $remainder < 2 ? 0 : 11 - $remainder;
    }
}
