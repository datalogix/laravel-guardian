<?php

namespace Datalogix\Guardian\Tests\Feature\TwoFactor;

use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Tests\TestCase;

class TwoFactorSetupMiddlewarePendingStateTest extends TestCase
{
    protected function fortresses(): array
    {
        return [Fortress::make()->basic()->twoFactor(requireSetupOnLogin: true)];
    }

    public function test_the_setup_page_is_reachable_by_an_unauthenticated_user_with_a_pending_setup(): void
    {
        $user = $this->createUser();
        Guardian::startPendingTwoFactorSetup($user, remember: true);

        $response = $this->get('/two-factor/setup');

        $response->assertOk();
    }

    public function test_the_setup_page_redirects_and_clears_state_for_a_deleted_pending_user(): void
    {
        $user = $this->createUser();
        Guardian::startPendingTwoFactorSetup($user, remember: true);
        $user->delete();

        $response = $this->get('/two-factor/setup');

        $response->assertRedirect();
        $this->assertStringContainsString('/login', $response->headers->get('Location'));
        $this->assertFalse(Guardian::hasPendingTwoFactorSetup());
    }
}
