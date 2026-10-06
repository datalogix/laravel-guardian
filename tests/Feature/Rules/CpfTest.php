<?php

namespace Datalogix\Guardian\Tests\Feature\Rules;

use Datalogix\Guardian\Rules\Cpf;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;

class CpfTest extends TestCase
{
    public static function validCpfs(): array
    {
        return [
            'formatted' => ['529.982.247-25'],
            'unformatted' => ['52998224725'],
        ];
    }

    public static function invalidCpfs(): array
    {
        return [
            'wrong length' => ['1234'],
            'all repeated digits' => ['111.111.111-11'],
            'invalid check digit' => ['529.982.247-26'],
            'letters among the digits' => ['529a982b247c25'],
        ];
    }

    #[DataProvider('validCpfs')]
    public function test_valid_cpf_passes(string $cpf): void
    {
        $this->assertFalse($this->validate($cpf)->fails());
    }

    #[DataProvider('invalidCpfs')]
    public function test_invalid_cpf_fails(string $cpf): void
    {
        $this->assertTrue($this->validate($cpf)->fails());
    }

    public function test_failure_message(): void
    {
        $this->assertSame(
            ['The document field must be a valid CPF.'],
            $this->validate('000')->errors()->get('document')
        );
    }

    protected function validate(string $cpf): \Illuminate\Validation\Validator
    {
        return Validator::make(['document' => $cpf], ['document' => [new Cpf]]);
    }
}
