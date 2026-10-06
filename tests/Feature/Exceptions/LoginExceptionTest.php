<?php

namespace Datalogix\Guardian\Tests\Feature\Exceptions;

use Datalogix\Guardian\Exceptions\LoginException;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Support\Facades\Lang;
use Illuminate\Validation\ValidationException;

class LoginExceptionTest extends TestCase
{
    public function test_invalid_carries_a_login_error(): void
    {
        $exception = LoginException::invalid();

        $this->assertInstanceOf(ValidationException::class, $exception);
        $this->assertArrayHasKey('login', $exception->errors());
        $this->assertSame([__('auth.failed')], $exception->errors()['login']);
    }

    public function test_cannot_access_falls_back_to_auth_failed_when_translation_missing(): void
    {
        $exception = LoginException::cannotAccess();

        $this->assertSame([__('auth.failed')], $exception->errors()['login']);
    }

    public function test_cannot_access_uses_its_own_translation_when_the_application_has_one(): void
    {
        Lang::addLines(['auth.cannot-access' => 'This account cannot sign in here.'], 'en');

        $exception = LoginException::cannotAccess();

        $this->assertSame(['This account cannot sign in here.'], $exception->errors()['login']);
    }

    public function test_rate_limited_includes_seconds_and_minutes(): void
    {
        $exception = LoginException::rateLimited(90);

        $this->assertArrayHasKey('login', $exception->errors());
        $this->assertSame([__('auth.throttle', ['seconds' => 90, 'minutes' => 2])], $exception->errors()['login']);
    }
}
