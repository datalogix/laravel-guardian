<?php

namespace Datalogix\Guardian\Tests\Feature\OAuth;

use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Support\OAuth\PendingOAuthRegistrationStep;
use Datalogix\Guardian\Tests\TestCase;

class PendingOAuthRegistrationStepTest extends TestCase
{
    public function test_has_pending_state_reflects_a_pending_oauth_registration(): void
    {
        $step = new PendingOAuthRegistrationStep;

        $this->assertFalse($step->hasPendingState());

        Guardian::startPendingOAuthRegistration(
            provider: 'github',
            providerUserId: 'gh-1',
            email: 'pending@example.com',
            name: 'Pending User',
            avatar: null,
        );

        $this->assertTrue($step->hasPendingState());
    }

    public function test_resolve_user_is_always_null(): void
    {
        $this->assertNull((new PendingOAuthRegistrationStep)->resolveUser());
    }

    public function test_is_valid_is_always_true(): void
    {
        $this->assertTrue((new PendingOAuthRegistrationStep)->isValid());
    }

    public function test_authorize_is_a_no_op(): void
    {
        $this->expectNotToPerformAssertions();

        (new PendingOAuthRegistrationStep)->authorize(null);
    }

    public function test_clear_removes_the_pending_registration_session(): void
    {
        Guardian::startPendingOAuthRegistration(
            provider: 'github',
            providerUserId: 'gh-2',
            email: 'pending2@example.com',
            name: 'Pending User Two',
            avatar: null,
        );

        $step = new PendingOAuthRegistrationStep;
        $this->assertTrue($step->hasPendingState());

        $step->clear();

        $this->assertFalse($step->hasPendingState());
        $this->assertFalse(Guardian::hasPendingOAuthRegistration());
    }
}
