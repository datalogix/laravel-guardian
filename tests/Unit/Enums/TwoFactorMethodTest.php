<?php

namespace Datalogix\Guardian\Tests\Unit\Enums;

use Datalogix\Guardian\Enums\TwoFactorMethod;
use PHPUnit\Framework\TestCase;

class TwoFactorMethodTest extends TestCase
{
    public function test_totp_does_not_require_delivery(): void
    {
        $this->assertFalse(TwoFactorMethod::Totp->requiresDelivery());
    }

    public function test_email_requires_delivery(): void
    {
        $this->assertTrue(TwoFactorMethod::Email->requiresDelivery());
    }

    public function test_sms_requires_delivery(): void
    {
        $this->assertTrue(TwoFactorMethod::Sms->requiresDelivery());
    }

    public function test_cases_have_expected_values(): void
    {
        $this->assertSame('totp', TwoFactorMethod::Totp->value);
        $this->assertSame('email', TwoFactorMethod::Email->value);
        $this->assertSame('sms', TwoFactorMethod::Sms->value);
    }
}
