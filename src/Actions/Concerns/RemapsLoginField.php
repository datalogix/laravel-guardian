<?php

namespace Datalogix\Guardian\Actions\Concerns;

use Datalogix\Guardian\Enums\IdentifierKey;
use Datalogix\Guardian\Guardian;

trait RemapsLoginField
{
    protected function remapLoginField(array $data, string $field = 'login'): array
    {
        if (array_key_exists('email', $data)) {
            $data['email'] = IdentifierKey::Email->normalize($data['email']);
        }

        if (! array_key_exists($field, $data)) {
            return $data;
        }

        $identifierKey = Guardian::getIdentifierKey();
        $data[$field] = $identifierKey->normalize($data[$field]);
        $column = $identifierKey->value;

        if ($column === $field) {
            return $data;
        }

        $data[$column] = $data[$field];
        unset($data[$field]);

        return $data;
    }
}
