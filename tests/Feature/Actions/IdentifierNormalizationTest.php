<?php

namespace Datalogix\Guardian\Tests\Feature\Actions;

use Datalogix\Guardian\Actions\ForgotPassword;
use Datalogix\Guardian\Actions\Login;
use Datalogix\Guardian\Actions\SignUp;
use Datalogix\Guardian\Enums\IdentifierKey;
use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Tests\Attributes\WithFortresses;
use Datalogix\Guardian\Tests\Fixtures\User;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;

class IdentifierNormalizationTest extends TestCase
{
    protected function withEmail(): array
    {
        return [Fortress::make()->basic()->signUp()->emailVerification(isRequired: false)];
    }

    protected function withCpf(): array
    {
        return [Fortress::make()->basic()->signUp()->identifierKey(IdentifierKey::CPF)->emailVerification(isRequired: false)];
    }

    protected function withCnpj(): array
    {
        return [Fortress::make()->basic()->signUp()->identifierKey(IdentifierKey::CNPJ)->emailVerification(isRequired: false)];
    }

    public static function normalizedValues(): array
    {
        return [
            'formatted CPF' => [IdentifierKey::CPF, '529.982.247-25', '52998224725'],
            'CPF with spaces' => [IdentifierKey::CPF, ' 529 982 247 25 ', '52998224725'],
            'formatted CNPJ' => [IdentifierKey::CNPJ, '11.222.333/0001-81', '11222333000181'],
            'alphanumeric CNPJ in lower case' => [IdentifierKey::CNPJ, '12.abc.345/01de-35', '12ABC34501DE35'],
            'e-mail with capitals and spaces' => [IdentifierKey::Email, ' Foo@Example.COM ', 'foo@example.com'],
            'username with capitals' => [IdentifierKey::Username, 'JohnDoe', 'johndoe'],
            'login with spaces' => [IdentifierKey::Login, ' MyLogin ', 'MyLogin'],
        ];
    }

    #[DataProvider('normalizedValues')]
    public function test_identifiers_are_normalized(IdentifierKey $key, string $typed, string $normalized): void
    {
        $this->assertSame($normalized, $key->normalize($typed));
    }

    protected function signUp(array $data): void
    {
        $data += ['name' => 'Test', 'password' => 'Secret-Pass-123!', 'password_confirmation' => 'Secret-Pass-123!', 'terms' => true];

        app(SignUp::class)(Validator::validate($data, SignUp::rules()));

        Guardian::auth()->logout();
    }

    #[WithFortresses('withCpf')]
    public function test_a_cpf_is_stored_by_its_digits_and_taken_whichever_way_it_is_typed(): void
    {
        $this->signUp(['login' => '529.982.247-25', 'email' => 'first@example.com']);

        $this->assertDatabaseHas('users', ['cpf' => '52998224725']);

        $validator = Validator::make(['login' => '52998224725', 'email' => 'second@example.com'], ['login' => SignUp::rules()['login']]);

        $this->assertTrue($validator->fails());
        $this->assertSame(__('validation.unique', ['attribute' => 'login']), $validator->errors()->first('login'));
    }

    #[WithFortresses('withCnpj')]
    public function test_an_alphanumeric_cnpj_is_stored_in_upper_case(): void
    {
        $this->signUp(['login' => '12.abc.345/01de-35', 'email' => 'company@example.com']);

        $this->assertDatabaseHas('users', ['cnpj' => '12ABC34501DE35']);
    }

    #[WithFortresses('withCpf')]
    public function test_a_cpf_signs_in_whichever_way_it_is_typed(): void
    {
        $this->signUp(['login' => '52998224725', 'email' => 'first@example.com']);

        foreach (['529.982.247-25', '52998224725'] as $typed) {
            app(Login::class)(['login' => $typed, 'password' => 'Secret-Pass-123!']);

            $this->assertTrue(Guardian::isAuthenticated(), $typed);
            Guardian::auth()->logout();
        }
    }

    #[WithFortresses('withEmail')]
    public function test_an_e_mail_is_stored_in_lower_case_and_unique_whatever_its_case(): void
    {
        $this->signUp(['login' => 'Foo@Example.com']);

        $this->assertDatabaseHas('users', ['email' => 'foo@example.com']);

        $validator = Validator::make(['login' => 'FOO@example.COM'], ['login' => SignUp::rules()['login']]);

        $this->assertTrue($validator->fails());
    }

    #[WithFortresses('withEmail')]
    public function test_an_e_mail_signs_in_whatever_its_case(): void
    {
        $this->signUp(['login' => 'foo@example.com']);

        app(Login::class)(['login' => 'FOO@Example.com', 'password' => 'Secret-Pass-123!']);

        $this->assertTrue(Guardian::isAuthenticated());
    }

    #[WithFortresses('withEmail')]
    public function test_a_reset_link_is_sent_whatever_the_case_of_the_e_mail(): void
    {
        Notification::fake();
        $this->signUp(['login' => 'foo@example.com']);

        app(ForgotPassword::class)(['login' => 'FOO@Example.com']);

        Notification::assertSentTo(User::whereEmail('foo@example.com')->firstOrFail(), ResetPassword::class);
    }

    protected function withUsername(): array
    {
        return [Fortress::make()->basic()->signUp()->identifierKey(IdentifierKey::Username)->emailVerification(isRequired: false)];
    }

    #[WithFortresses('withUsername')]
    public function test_a_username_signs_in_whatever_its_case(): void
    {
        $this->signUp(['login' => 'JohnDoe', 'email' => 'john@example.com']);

        $this->assertDatabaseHas('users', ['username' => 'johndoe']);

        $data = Validator::validate(['login' => 'JOHNDOE', 'password' => 'Secret-Pass-123!'], Login::rules());
        app(Login::class)($data);

        $this->assertTrue(Guardian::isAuthenticated());
    }

    public function test_a_value_that_is_not_text_is_left_as_it_is(): void
    {
        $this->assertNull(IdentifierKey::CPF->normalize(null));
        $this->assertSame(123, IdentifierKey::Email->normalize(123));
    }
}
