<?php

namespace Datalogix\Guardian\Tests\Feature\Exceptions;

use Datalogix\Guardian\Exceptions\TwoFactorChallengeException;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Validation\ValidationException;

class TwoFactorChallengeExceptionTest extends TestCase
{
    public function test_invalid(): void
    {
        $exception = TwoFactorChallengeException::invalid();

        $this->assertInstanceOf(ValidationException::class, $exception);
        $this->assertSame([__('auth.failed')], $exception->errors()['code']);
    }

    public function test_not_pending(): void
    {
        $this->assertSame([__('auth.failed')], TwoFactorChallengeException::notPending()->errors()['code']);
    }

    public function test_rate_limited(): void
    {
        $exception = TwoFactorChallengeException::rateLimited(10);

        $this->assertSame([__('auth.throttle', ['seconds' => 10, 'minutes' => 1])], $exception->errors()['code']);
    }
}
