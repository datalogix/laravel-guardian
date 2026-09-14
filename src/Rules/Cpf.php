<?php

namespace Datalogix\Guardian\Rules;

use Closure;
use Datalogix\Guardian\Rules\Concerns\ValidatesCheckDigit;
use Illuminate\Contracts\Validation\ValidationRule;

class Cpf implements ValidationRule
{
    use ValidatesCheckDigit;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $this->isValid((string) $value)) {
            $fail(__('The :attribute field must be a valid CPF.'));
        }
    }

    private function isValid(string $value): bool
    {
        $cpf = preg_replace('/\D/', '', $value);

        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        $weights = [
            [10, 9, 8, 7, 6, 5, 4, 3, 2],
            [11, 10, 9, 8, 7, 6, 5, 4, 3, 2],
        ];

        foreach ($weights as $index => $digitWeights) {
            $length = 9 + $index;

            if ($this->checkDigit($cpf, $length, $digitWeights) !== (int) $cpf[$length]) {
                return false;
            }
        }

        return true;
    }
}
