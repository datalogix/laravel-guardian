<?php

namespace Datalogix\Guardian\Tests\Feature\Exceptions;

use Datalogix\Guardian\Enums\TwoFactorMethod;
use Datalogix\Guardian\Exceptions\TwoFactorDeliveryException;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Validation\ValidationException;

class TwoFactorDeliveryExceptionTest extends TestCase
{
    public function test_unavailable_method_for_sms_mentions_a_channel(): void
    {
        $exception = TwoFactorDeliveryException::unavailableMethod(TwoFactorMethod::Sms);

        $this->assertInstanceOf(ValidationException::class, $exception);
        $this->assertStringContainsString('SMS two-factor delivery is not configured', $exception->errors()['code'][0]);
    }

    public function test_unavailable_method_for_email_mentions_the_method(): void
    {
        $exception = TwoFactorDeliveryException::unavailableMethod(TwoFactorMethod::Email);

        $this->assertSame(
            [__('Two-factor delivery is not configured for method: :method', ['method' => 'email'])],
            $exception->errors()['code']
        );
    }

    public function test_missing_recipient(): void
    {
        $exception = TwoFactorDeliveryException::missingRecipient(TwoFactorMethod::Sms);

        $this->assertSame(
            [__('Could not determine a recipient for two-factor method: :method', ['method' => 'sms'])],
            $exception->errors()['code']
        );
    }
}
