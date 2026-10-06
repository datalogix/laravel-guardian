<?php

namespace Datalogix\Guardian\Tests\Feature\Framework\Livewire\Pages;

use Datalogix\Guardian\Actions\Login;
use Datalogix\Guardian\Enums\AuthFlowResult;
use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Framework\Livewire\Pages\TwoFactorSetup;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Group;
use PragmaRX\Google2FA\Google2FA;

#[Group('livewire')]
class TwoFactorSetupForcedOnLoginTest extends TestCase
{
    protected function fortresses(): array
    {
        return [Fortress::make()->basic()->twoFactor(requireSetupOnLogin: true)];
    }

    public function test_completing_forced_setup_and_continuing_authenticates_the_user(): void
    {
        $user = $this->createUser(['password' => Hash::make('secret123')]);

        $result = app(Login::class)(['login' => $user->email, 'password' => 'secret123']);
        $this->assertSame(AuthFlowResult::SetupRequired, $result);
        $this->assertFalse(Guardian::isAuthenticated());

        $component = Livewire::test(TwoFactorSetup::class)->call('prepare');
        $code = app(Google2FA::class)->getCurrentOtp($component->get('secret'));

        $component->set('code', $code)->call('enable');
        $this->assertTrue($component->get('awaitingContinueAfterSetup'));

        $component->call('continueAfterSetup')->assertRedirect();

        $this->assertTrue(Guardian::isAuthenticated());
        $this->assertTrue(Guardian::user()->is($user->fresh()));
    }

    public function test_continue_after_setup_is_a_no_op_when_not_awaiting(): void
    {
        $this->actingAs($this->createUser());
        session()->put('auth.password_confirmed_at', time());

        Livewire::test(TwoFactorSetup::class)->call('continueAfterSetup')->assertNoRedirect();

    }
}
