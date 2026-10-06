<?php

namespace Datalogix\Guardian\Tests\Feature\TwoFactor;

use Datalogix\Guardian\Support\TwoFactor\Totp;
use Datalogix\Guardian\Tests\TestCase;
use PragmaRX\Google2FA\Google2FA;

class TotpTest extends TestCase
{
    protected Totp $totp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->totp = new Totp;
    }

    public function test_generate_secret_has_requested_length(): void
    {
        $secret = $this->totp->generateSecret(32);

        $this->assertNotEmpty($secret);
        $this->assertNotSame($secret, $this->totp->generateSecret(32));
    }

    public function test_make_otp_auth_uri_contains_issuer_and_account(): void
    {
        $secret = $this->totp->generateSecret();

        $uri = $this->totp->makeOtpAuthUri($secret, 'user@example.com', 'Guardian');

        $this->assertStringStartsWith('otpauth://totp/', $uri);
        $this->assertStringContainsString('Guardian', $uri);
        $this->assertStringContainsString('user%40example.com', $uri);
    }

    public function test_make_otp_auth_uri_defaults_issuer_to_app_name(): void
    {
        $secret = $this->totp->generateSecret();

        $uri = $this->totp->makeOtpAuthUri($secret, 'user@example.com');

        $this->assertStringContainsString(rawurlencode(config('app.name')), $uri);
    }

    public function test_verify_accepts_the_current_valid_code(): void
    {
        $secret = $this->totp->generateSecret();
        $code = app(Google2FA::class)->getCurrentOtp($secret);

        $this->assertNotFalse($this->totp->verify($secret, $code));
    }

    public function test_verify_rejects_an_invalid_code(): void
    {
        $secret = $this->totp->generateSecret();

        $this->assertFalse($this->totp->verify($secret, '000000'));
    }

    public function test_verify_rejects_malformed_codes(): void
    {
        $secret = $this->totp->generateSecret();

        $this->assertFalse($this->totp->verify($secret, 'abcdef'));
        $this->assertFalse($this->totp->verify($secret, '12345'));
        $this->assertFalse($this->totp->verify($secret, ''));
    }

    public function test_verify_strips_whitespace(): void
    {
        $secret = $this->totp->generateSecret();
        $code = app(Google2FA::class)->getCurrentOtp($secret);

        $this->assertNotFalse($this->totp->verify($secret, ' '.$code.' '));
    }

    public function test_resolve_account_label_prefers_email_over_identifier(): void
    {
        $user = $this->createUser(['email' => 'label@example.com']);

        $this->assertSame('label@example.com', $this->totp->resolveAccountLabel($user));
    }

    public function test_resolve_account_label_falls_back_to_default(): void
    {
        $this->assertSame('user', $this->totp->resolveAccountLabel(new \stdClass, 'user'));
    }
}
