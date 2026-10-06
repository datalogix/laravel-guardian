<?php

namespace Datalogix\Guardian\Tests\Unit\Support\TwoFactor;

use Datalogix\Guardian\Support\TwoFactor\RecoveryCodes;
use PHPUnit\Framework\TestCase;

class RecoveryCodesTest extends TestCase
{
    public function test_generate_returns_requested_total(): void
    {
        $codes = (new RecoveryCodes)->generate(8);

        $this->assertCount(8, $codes);
    }

    public function test_generate_default_total_is_eight(): void
    {
        $this->assertCount(8, (new RecoveryCodes)->generate());
    }

    public function test_generated_codes_are_unique_hex_strings(): void
    {
        $codes = (new RecoveryCodes)->generate(20);

        $this->assertCount(20, array_unique($codes));

        foreach ($codes as $code) {
            $this->assertMatchesRegularExpression('/^[0-9a-f]{24}$/', $code);
        }
    }
}
