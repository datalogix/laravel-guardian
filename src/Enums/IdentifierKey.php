<?php

namespace Datalogix\Guardian\Enums;

use Datalogix\Guardian\Rules\Cnpj;
use Datalogix\Guardian\Rules\Cpf;

enum IdentifierKey: string
{
    case CPF = 'cpf';
    case CNPJ = 'cnpj';
    case Email = 'email';
    case Login = 'login';
    case Username = 'username';

    public function rules(array $extra = []): array
    {
        return match ($this) {
            self::CPF => ['required', new Cpf, ...$extra],
            self::CNPJ => ['required', new Cnpj, ...$extra],
            self::Email => ['required', 'string', 'email', 'max:255', ...$extra],
            self::Login => ['required', 'string', 'min:5', 'max:255', ...$extra],
            self::Username => ['required', 'string', 'min:5', 'max:20', 'lowercase', 'alpha_num', ...$extra],
        };
    }
}
