<?php

namespace Datalogix\Guardian\Tests\Feature\TwoFactor;

use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Support\TwoFactor\TrustedDevices;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Support\Facades\Schema;

class TrustedDevicesUnavailableTest extends TestCase
{
    protected TrustedDevices $trustedDevices;

    protected function setUp(): void
    {
        parent::setUp();

        // Simulates a consuming app that never enabled the trusted-devices
        // feature, so Guardian's conditional migration never ran.
        Schema::dropIfExists('two_factor_trusted_devices');

        $this->trustedDevices = new TrustedDevices;
    }

    protected function fortress()
    {
        return Guardian::getCurrentOrDefaultFortress();
    }

    public function test_is_available_is_false_without_the_table(): void
    {
        $this->assertFalse($this->trustedDevices->isAvailable());
    }

    public function test_issue_returns_null(): void
    {
        $this->assertNull($this->trustedDevices->issue($this->fortress(), $this->createUser(), 30));
    }

    public function test_touch_if_valid_returns_false(): void
    {
        $this->assertFalse($this->trustedDevices->touchIfValid($this->fortress(), $this->createUser(), 1, 'token'));
    }

    public function test_list_returns_an_empty_array(): void
    {
        $this->assertSame([], $this->trustedDevices->list($this->fortress(), $this->createUser()));
    }

    public function test_revoke_returns_false(): void
    {
        $this->assertFalse($this->trustedDevices->revoke(1, $this->fortress(), $this->createUser()));
    }

    public function test_revoke_all_returns_zero(): void
    {
        $this->assertSame(0, $this->trustedDevices->revokeAll($this->fortress(), $this->createUser()));
    }

    public function test_prune_returns_zero(): void
    {
        $this->assertSame(0, $this->trustedDevices->prune());
    }
}
