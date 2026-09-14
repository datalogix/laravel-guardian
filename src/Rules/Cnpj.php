<?php

namespace Datalogix\Guardian\Rules;

use Closure;
use Datalogix\Guardian\Rules\Concerns\ValidatesCheckDigit;
use Illuminate\Contracts\Validation\ValidationRule;

class Cnpj implements ValidationRule
{
    use ValidatesCheckDigit;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $this->isValid((string) $value)) {
            $fail(__('The :attribute field must be a valid CNPJ.'));
        }
    }

    private function isValid(string $value): bool
    {
        $cnpj = strtoupper((string) preg_replace('/[^0-9A-Za-z]/', '', $value));

        if (strlen($cnpj) !== 14 || preg_match('/^(.)\1{13}$/', $cnpj)) {
            return false;
        }

        if (! ctype_digit(substr($cnpj, 12, 2))) {
            return false;
        }

        $weights = [
            [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2],
            [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2],
        ];

        foreach ($weights as $index => $digitWeights) {
            $length = 12 + $index;

            if ($this->checkDigit($cnpj, $length, $digitWeights) !== (int) $cnpj[$length]) {
                return false;
            }
        }

        return true;
    }
}
