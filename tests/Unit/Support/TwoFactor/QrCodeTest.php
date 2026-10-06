<?php

namespace Datalogix\Guardian\Tests\Unit\Support\TwoFactor;

use Datalogix\Guardian\Support\TwoFactor\QrCode;
use PHPUnit\Framework\TestCase;

class QrCodeTest extends TestCase
{
    public function test_svg_generates_valid_svg_markup(): void
    {
        $svg = (new QrCode)->svg('otpauth://totp/Guardian:user@example.com?secret=ABCDEF&issuer=Guardian');

        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringContainsString('</svg>', $svg);
    }

    public function test_svg_respects_the_requested_size(): void
    {
        $svg = (new QrCode)->svg('content', 300);

        $this->assertStringContainsString('width="300', $svg);
        $this->assertStringContainsString('height="300', $svg);
    }
}
