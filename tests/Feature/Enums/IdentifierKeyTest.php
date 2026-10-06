<?php

namespace Datalogix\Guardian\Tests\Feature\Enums;

use Datalogix\Guardian\Enums\IdentifierKey;
use Datalogix\Guardian\Rules\Cnpj;
use Datalogix\Guardian\Rules\Cpf;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Support\Facades\Validator;

class IdentifierKeyTest extends TestCase
{
    public function test_cpf_rules_require_a_valid_cpf_rule(): void
    {
        $rules = IdentifierKey::CPF->rules();

        $this->assertSame(['required'], array_filter($rules, fn ($rule) => $rule === 'required'));
        $this->assertTrue(collect($rules)->contains(fn ($rule) => $rule instanceof Cpf));
    }

    public function test_cnpj_rules_require_a_valid_cnpj_rule(): void
    {
        $rules = IdentifierKey::CNPJ->rules();

        $this->assertTrue(collect($rules)->contains(fn ($rule) => $rule instanceof Cnpj));
    }

    public function test_email_rules(): void
    {
        $validator = Validator::make(['login' => 'not-an-email'], ['login' => IdentifierKey::Email->rules()]);

        $this->assertTrue($validator->fails());

        $validator = Validator::make(['login' => 'user@example.com'], ['login' => IdentifierKey::Email->rules()]);

        $this->assertFalse($validator->fails());
    }

    public function test_login_rules_enforce_length(): void
    {
        $validator = Validator::make(['login' => 'ab'], ['login' => IdentifierKey::Login->rules()]);
        $this->assertTrue($validator->fails());

        $validator = Validator::make(['login' => 'abcde'], ['login' => IdentifierKey::Login->rules()]);
        $this->assertFalse($validator->fails());
    }

    public function test_username_rules_enforce_lowercase_alpha_numeric(): void
    {
        $validator = Validator::make(['login' => 'Not_Valid!'], ['login' => IdentifierKey::Username->rules()]);
        $this->assertTrue($validator->fails());

        $validator = Validator::make(['login' => 'validuser1'], ['login' => IdentifierKey::Username->rules()]);
        $this->assertFalse($validator->fails());
    }

    public function test_rules_accept_extra_rules(): void
    {
        $rules = IdentifierKey::Email->rules(['confirmed']);

        $this->assertContains('confirmed', $rules);
    }

    public function test_all_cases_have_string_values(): void
    {
        $this->assertSame([
            'cpf', 'cnpj', 'email', 'login', 'username',
        ], array_map(fn (IdentifierKey $case) => $case->value, IdentifierKey::cases()));
    }
}
