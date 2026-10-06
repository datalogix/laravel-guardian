<?php

namespace Datalogix\Guardian\Enums;

use Closure;
use Datalogix\Guardian\Rules\Cnpj;
use Datalogix\Guardian\Rules\Cpf;
use Illuminate\Support\Str;

enum IdentifierKey: string
{
    case CPF = 'cpf';
    case CNPJ = 'cnpj';
    case Email = 'email';
    case Login = 'login';
    case Username = 'username';

    public function normalize(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        return match ($this) {
            self::CPF => (string) preg_replace('/[^0-9]/', '', $value),
            self::CNPJ => strtoupper((string) preg_replace('/[^0-9A-Za-z]/', '', $value)),
            self::Email, self::Username => Str::lower(trim($value)),
            self::Login => trim($value),
        };
    }

    public function unique(string $modelClass, ?string $column = null): Closure
    {
        $column ??= $this->value;

        return function (string $attribute, mixed $value, Closure $fail) use ($modelClass, $column) {
            if ($modelClass::query()->where($column, $this->normalize($value))->exists()) {
                $fail('validation.unique')->translate();
            }
        };
    }

    public function rules(array $extra = []): array
    {
        return match ($this) {
            self::CPF => ['required', new Cpf, ...$extra],
            self::CNPJ => ['required', new Cnpj, ...$extra],
            self::Email => ['required', 'string', 'email', 'max:255', ...$extra],
            self::Login => ['required', 'string', 'min:5', 'max:255', ...$extra],
            // No "lowercase" rule: the value is normalized to lower case.
            self::Username => ['required', 'string', 'min:5', 'max:20', 'alpha_num', ...$extra],
        };
    }
}
