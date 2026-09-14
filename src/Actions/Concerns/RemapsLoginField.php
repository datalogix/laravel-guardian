<?php

namespace Datalogix\Guardian\Actions\Concerns;

use Datalogix\Guardian\Guardian;

trait RemapsLoginField
{
    /**
     * Remap the generic `login` form field to the fortress's real identifier column
     * (email/username/cpf/cnpj/login), so it lands on the right model attribute
     * and the right column when queried (credential lookup, uniqueness checks, ...).
     */
    protected function remapLoginField(array $data, string $field = 'login'): array
    {
        if (! array_key_exists($field, $data)) {
            return $data;
        }

        $column = Guardian::getIdentifierKey()->value;

        if ($column === $field) {
            return $data;
        }

        $data[$column] = $data[$field];
        unset($data[$field]);

        return $data;
    }
}
