<?php

namespace Datalogix\Guardian\Tests\Feature\Exceptions;

use Datalogix\Guardian\Exceptions\PasswordConfirmationException;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;

class PasswordConfirmationExceptionTest extends TestCase
{
    public static function messageFactories(): array
    {
        return [
            'requiredForEnablingTwoFactor' => ['requiredForEnablingTwoFactor', 'Please confirm your password before enabling two-factor authentication.'],
            'requiredForDisablingTwoFactor' => ['requiredForDisablingTwoFactor', 'Please confirm your password before disabling two-factor authentication.'],
            'requiredForRegeneratingRecoveryCodes' => ['requiredForRegeneratingRecoveryCodes', 'Please confirm your password before regenerating two-factor recovery codes.'],
            'requiredForDisconnectingOAuth' => ['requiredForDisconnectingOAuth', 'Please confirm your password before disconnecting this provider.'],
        ];
    }

    #[DataProvider('messageFactories')]
    public function test_static_factories_carry_a_password_error(string $factory, string $message): void
    {
        $exception = PasswordConfirmationException::{$factory}();

        $this->assertInstanceOf(ValidationException::class, $exception);
        $this->assertSame([$message], $exception->errors()['password']);
    }

    public function test_invalid(): void
    {
        $exception = PasswordConfirmationException::invalid();

        $this->assertInstanceOf(ValidationException::class, $exception);
        $this->assertSame([__('auth.password')], $exception->errors()['password']);
    }

    public function test_rate_limited(): void
    {
        $exception = PasswordConfirmationException::rateLimited(20);

        $this->assertSame(['Too many attempts. Please try again in 20 seconds.'], $exception->errors()['password']);
    }
}
