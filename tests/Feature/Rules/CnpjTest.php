<?php

namespace Datalogix\Guardian\Tests\Feature\Rules;

use Datalogix\Guardian\Rules\Cnpj;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;

class CnpjTest extends TestCase
{
    public static function validCnpjs(): array
    {
        return [
            'formatted' => ['11.222.333/0001-81'],
            'unformatted' => ['11222333000181'],
            'alphanumeric' => ['12.ABC.345/01DE-35'],
            'alphanumeric in lower case' => ['12abc34501de35'],
        ];
    }

    public static function invalidCnpjs(): array
    {
        return [
            'wrong length' => ['1234'],
            'all repeated characters' => ['11.111.111/1111-11'],
            'invalid check digit' => ['11.222.333/0001-82'],
            // 14 characters, not all repeated, but the check digits are letters.
            'non-digit check digits' => ['112223330001AB'],
            'characters other than the usual punctuation' => ['11.222.333/0001-81#'],
        ];
    }

    #[DataProvider('validCnpjs')]
    public function test_valid_cnpj_passes(string $cnpj): void
    {
        $this->assertFalse($this->validate($cnpj)->fails());
    }

    #[DataProvider('invalidCnpjs')]
    public function test_invalid_cnpj_fails(string $cnpj): void
    {
        $this->assertTrue($this->validate($cnpj)->fails());
    }

    public function test_failure_message(): void
    {
        $this->assertSame(
            ['The document field must be a valid CNPJ.'],
            $this->validate('000')->errors()->get('document')
        );
    }

    protected function validate(string $cnpj): \Illuminate\Validation\Validator
    {
        return Validator::make(['document' => $cnpj], ['document' => [new Cnpj]]);
    }
}
