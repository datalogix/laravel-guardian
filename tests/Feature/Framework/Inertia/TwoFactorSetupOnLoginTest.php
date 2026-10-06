<?php

namespace Datalogix\Guardian\Tests\Feature\Framework\Inertia;

use Datalogix\Guardian\Fortress;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Group;
use PragmaRX\Google2FA\Google2FA;

#[Group('inertia')]
class TwoFactorSetupOnLoginTest extends InertiaTestCase
{
    protected function fortresses(): array
    {
        return [Fortress::make()->inertia()->basic()->twoFactor(requireSetupOnLogin: true)];
    }

    public function test_a_user_without_two_factor_finishes_the_setup_before_being_signed_in(): void
    {
        $user = $this->createUser(['email' => 'setup@example.com', 'password' => Hash::make('secret123')]);

        $this->inertiaPost('/login', ['login' => 'setup@example.com', 'password' => 'secret123'])
            ->assertRedirect(url('/two-factor/setup'));

        $this->assertGuest();

        $this->assertPage($this->inertiaGet('/two-factor/setup'), 'Guardian/TwoFactorSetup', ['enabled' => false]);

        $this->inertiaPost('/two-factor/setup/prepare')->assertRedirect();
        $secret = $this->inertiaGet('/two-factor/setup')->json('props.secret');
        $this->assertNotEmpty($secret);

        $this->inertiaPost('/two-factor/setup/enable', ['code' => app(Google2FA::class)->getCurrentOtp($secret)])
            ->assertRedirect();

        $page = $this->inertiaGet('/two-factor/setup');

        $this->assertPage($page, 'Guardian/TwoFactorSetup', [
            'enabled' => true,
            'awaitingContinueAfterSetup' => true,
        ]);
        $this->assertCount(8, $page->json('props.recoveryCodes'));
        $this->assertAuthenticatedAs($user);

        $this->inertiaPost('/two-factor/setup/continue')->assertRedirect();
    }

    public function test_continue_sends_guests_to_the_login(): void
    {
        $this->inertiaPost('/two-factor/setup/continue')->assertRedirect();
    }
}
