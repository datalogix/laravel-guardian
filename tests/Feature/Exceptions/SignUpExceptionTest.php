<?php

namespace Datalogix\Guardian\Tests\Feature\Exceptions;

use Datalogix\Guardian\Exceptions\SignUpException;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Validation\ValidationException;

class SignUpExceptionTest extends TestCase
{
    public function test_rate_limited(): void
    {
        $exception = SignUpException::rateLimited(30);

        $this->assertInstanceOf(ValidationException::class, $exception);
        $this->assertSame(['Too many attempts. Please try again in 30 seconds.'], $exception->errors()['login']);
    }

    public function test_cannot_access(): void
    {
        $this->assertSame([__('auth.failed')], SignUpException::cannotAccess()->errors()['login']);
    }

    public function test_email_already_exists(): void
    {
        $exception = SignUpException::emailAlreadyExists();

        $this->assertSame(
            [__('validation.unique', ['attribute' => 'email'])],
            $exception->errors()['login']
        );
    }

    public function test_unable_to_register(): void
    {
        $this->assertSame(['We could not create your account. Please try again later.'], SignUpException::unableToRegister()->errors()['login']);
    }
}
