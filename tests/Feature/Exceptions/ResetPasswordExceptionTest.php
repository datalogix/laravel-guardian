<?php

namespace Datalogix\Guardian\Tests\Feature\Exceptions;

use Datalogix\Guardian\Exceptions\ResetPasswordException;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Validation\ValidationException;

class ResetPasswordExceptionTest extends TestCase
{
    public function test_for_status_translates_the_password_broker_status(): void
    {
        $exception = ResetPasswordException::forStatus('passwords.token');

        $this->assertInstanceOf(ValidationException::class, $exception);
        $this->assertSame([__('passwords.token')], $exception->errors()['login']);
    }

    public function test_rate_limited(): void
    {
        $exception = ResetPasswordException::rateLimited(45);

        $this->assertSame(['Too many attempts. Please try again in 45 seconds.'], $exception->errors()['login']);
    }
}
