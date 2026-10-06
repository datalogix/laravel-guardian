<?php

namespace Datalogix\Guardian\Tests\Feature\Exceptions;

use Datalogix\Guardian\Exceptions\TwoFactorSetupException;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;

class TwoFactorSetupExceptionTest extends TestCase
{
    public static function genericFailureFactories(): array
    {
        return [
            'invalidCode' => ['invalidCode'],
            'missingPendingSecret' => ['missingPendingSecret'],
        ];
    }

    #[DataProvider('genericFailureFactories')]
    public function test_static_factories_carry_a_generic_code_error(string $factory): void
    {
        $exception = TwoFactorSetupException::{$factory}();

        $this->assertInstanceOf(ValidationException::class, $exception);
        $this->assertSame([__('auth.failed')], $exception->errors()['code']);
    }

    public function test_invalid_account_label(): void
    {
        $this->assertSame(
            ['Could not determine a valid account label for two-factor setup.'],
            TwoFactorSetupException::invalidAccountLabel()->errors()['code']
        );
    }

    public function test_rate_limited(): void
    {
        $exception = TwoFactorSetupException::rateLimited(5);

        $this->assertSame(['Too many attempts. Please try again in 5 seconds.'], $exception->errors()['code']);
    }

    public function test_unable_to_store_secret_does_not_blame_the_user(): void
    {
        $this->assertSame(
            ['We could not save your two-factor settings. Please try again later.'],
            TwoFactorSetupException::unableToStoreSecret()->errors()['code']
        );
    }
}
