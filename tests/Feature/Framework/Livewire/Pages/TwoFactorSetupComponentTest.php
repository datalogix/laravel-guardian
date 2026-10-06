<?php

namespace Datalogix\Guardian\Tests\Feature\Framework\Livewire\Pages;

use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Framework\Livewire\Pages\TwoFactorSetup;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Support\TwoFactor\TrustedDevices;
use Datalogix\Guardian\Support\TwoFactor\TwoFactorUser;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Group;
use PragmaRX\Google2FA\Google2FA;

#[Group('livewire')]
class TwoFactorSetupComponentTest extends TestCase
{
    protected function fortresses(): array
    {
        return [Fortress::make()->basic()->twoFactor(rememberOnDevice: true)];
    }

    protected function authenticatedUserWithConfirmedPassword()
    {
        $user = $this->createUser(['password' => Hash::make('secret123')]);
        $this->actingAs($user);
        session()->put('auth.password_confirmed_at', time());

        return $user;
    }

    public function test_it_renders_for_authenticated_users(): void
    {
        $this->authenticatedUserWithConfirmedPassword();

        Livewire::test(TwoFactorSetup::class)->assertOk();
    }

    public function test_prepare_generates_a_pending_secret(): void
    {
        $this->authenticatedUserWithConfirmedPassword();

        Livewire::test(TwoFactorSetup::class)
            ->call('prepare')
            ->assertSet('enabled', false)
            ->assertNotSet('secret', null);
    }

    public function test_enable_activates_two_factor_and_shows_recovery_codes(): void
    {
        $user = $this->authenticatedUserWithConfirmedPassword();

        $component = Livewire::test(TwoFactorSetup::class)->call('prepare');
        $secret = $component->get('secret');
        $code = app(Google2FA::class)->getCurrentOtp($secret);

        $component->set('code', $code)->call('enable');

        $this->assertTrue($component->get('enabled'));
        $this->assertCount(8, $component->get('recoveryCodes'));

        $fortress = Guardian::getCurrentOrDefaultFortress();
        $this->assertTrue(app(TwoFactorUser::class)->hasTwoFactorEnabled($user->fresh(), $fortress));
    }

    public function test_enable_fails_with_an_invalid_code(): void
    {
        $this->authenticatedUserWithConfirmedPassword();

        Livewire::test(TwoFactorSetup::class)
            ->call('prepare')
            ->set('code', '000000')
            ->call('enable')
            ->assertHasErrors('code');
    }

    public function test_disable_deactivates_two_factor(): void
    {
        $user = $this->authenticatedUserWithConfirmedPassword();

        $component = Livewire::test(TwoFactorSetup::class)->call('prepare');
        $code = app(Google2FA::class)->getCurrentOtp($component->get('secret'));
        $component->set('code', $code)->call('enable');

        $component->call('disable');

        $this->assertFalse($component->get('enabled'));

        $fortress = Guardian::getCurrentOrDefaultFortress();
        $this->assertFalse(app(TwoFactorUser::class)->hasTwoFactorEnabled($user->fresh(), $fortress));
    }

    public function test_regenerate_recovery_codes_returns_a_fresh_set(): void
    {
        $this->authenticatedUserWithConfirmedPassword();

        $component = Livewire::test(TwoFactorSetup::class)->call('prepare');
        $code = app(Google2FA::class)->getCurrentOtp($component->get('secret'));
        $component->set('code', $code)->call('enable');

        $firstCodes = $component->get('recoveryCodes');

        $component->call('regenerateRecoveryCodes');

        $this->assertNotSame($firstCodes, $component->get('recoveryCodes'));
        $this->assertCount(8, $component->get('recoveryCodes'));
    }

    public function test_revoke_trusted_device_removes_it_from_the_list(): void
    {
        $user = $this->authenticatedUserWithConfirmedPassword();

        $component = Livewire::test(TwoFactorSetup::class)->call('prepare');
        $code = app(Google2FA::class)->getCurrentOtp($component->get('secret'));
        $component->set('code', $code)->call('enable');

        $fortress = Guardian::getCurrentOrDefaultFortress();
        $issued = (new TrustedDevices)->issue($fortress, $user->fresh(), 30);

        $component->call('revokeTrustedDevice', $issued['id']);

        $this->assertDatabaseHas('two_factor_trusted_devices', ['id' => $issued['id']]);
        $this->assertNotNull(DB::table('two_factor_trusted_devices')->where('id', $issued['id'])->value('revoked_at'));
    }

    public function test_revoke_all_trusted_devices_clears_them(): void
    {
        $user = $this->authenticatedUserWithConfirmedPassword();

        $component = Livewire::test(TwoFactorSetup::class)->call('prepare');
        $code = app(Google2FA::class)->getCurrentOtp($component->get('secret'));
        $component->set('code', $code)->call('enable');

        $fortress = Guardian::getCurrentOrDefaultFortress();
        $trustedDevices = new TrustedDevices;
        $trustedDevices->issue($fortress, $user->fresh(), 30);
        $trustedDevices->issue($fortress, $user->fresh(), 30);

        $component->call('revokeAllTrustedDevices');

        $this->assertCount(0, $trustedDevices->list($fortress, $user->fresh()));
    }
}
