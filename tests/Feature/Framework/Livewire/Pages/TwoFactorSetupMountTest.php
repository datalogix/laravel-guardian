<?php

namespace Datalogix\Guardian\Tests\Feature\Framework\Livewire\Pages;

use Datalogix\Guardian\Actions\EnableTwoFactor;
use Datalogix\Guardian\Actions\PrepareTwoFactorSetup;
use Datalogix\Guardian\Enums\TwoFactorMethod;
use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Framework\Livewire\Pages\TwoFactorSetup;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Support\TwoFactor\TwoFactorUser;
use Datalogix\Guardian\Tests\Attributes\WithFortresses;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Group;
use PragmaRX\Google2FA\Google2FA;

#[Group('livewire')]
class TwoFactorSetupMountTest extends TestCase
{
    protected function fortresses(): array
    {
        return [Fortress::make()->basic()->twoFactor()];
    }

    protected function usingEmail(): array
    {
        return [Fortress::make()->basic()->twoFactor(method: TwoFactorMethod::Email)];
    }

    protected function signIn()
    {
        $user = $this->createUser();
        $this->actingAs($user);
        session()->put('auth.password_confirmed_at', time());

        return $user;
    }

    protected function enableTwoFactorFor($user): void
    {
        $setup = app(PrepareTwoFactorSetup::class)($user);
        $code = app(Google2FA::class)->getCurrentOtp($setup['secret']);
        app(EnableTwoFactor::class)($user, ['code' => $code]);
    }

    public function test_guests_without_a_pending_setup_are_redirected_to_login(): void
    {
        Livewire::test(TwoFactorSetup::class)->assertRedirect();
    }

    public function test_mounting_for_an_already_enabled_user_reads_back_its_recovery_code_count(): void
    {
        $this->enableTwoFactorFor($this->signIn());

        // A fresh mount reads the codes back from storage.
        $component = Livewire::test(TwoFactorSetup::class)->assertOk();

        $this->assertTrue($component->get('enabled'));
        $this->assertTrue($component->get('canManageRecoveryCodes'));
        $this->assertSame(8, $component->get('recoveryCodesCount'));
        $this->assertSame([], $component->get('recoveryCodes'));
    }

    public function test_remounting_with_a_pending_totp_setup_rebuilds_its_qr_code(): void
    {
        app(PrepareTwoFactorSetup::class)($this->signIn());

        $component = Livewire::test(TwoFactorSetup::class)->assertOk();

        $this->assertFalse($component->get('enabled'));
        $this->assertNotNull($component->get('secret'));
        $this->assertNotNull($component->get('uri'));
        $this->assertStringContainsString('<svg', $component->get('qrSvg'));
    }

    #[WithFortresses('usingEmail')]
    public function test_remounting_with_a_pending_email_setup_restores_its_state_without_a_qr_code(): void
    {
        Notification::fake();

        app(PrepareTwoFactorSetup::class)($this->signIn(), TwoFactorMethod::Email);

        $component = Livewire::test(TwoFactorSetup::class)->assertOk();

        $this->assertFalse($component->get('enabled'));
        $this->assertSame(TwoFactorMethod::Email, $component->get('method'));
        $this->assertNotNull($component->get('secret'));
        $this->assertNull($component->get('qrSvg'));
        $this->assertNull($component->get('uri'));
    }

    public function test_it_reports_the_secret_as_unreadable_instead_of_crashing(): void
    {
        $user = $this->signIn();
        $this->enableTwoFactorFor($user);

        DB::table('users')->where('id', $user->id)->update(['two_factor_secret' => 'not-encrypted-data']);

        // TwoFactorUser is scoped: a fresh one, like a new request, does not hold the old secret.
        $this->app->forgetInstance(TwoFactorUser::class);

        // actingAs() pinned the user from before the corruption.
        $this->actingAs($user->fresh());

        // mount() directly: rendering the view needs the optional tallkit package.
        $component = new TwoFactorSetup;
        $component->mount();

        $this->assertTrue($component->enabled);
        $this->assertTrue($component->secretUnreadable);
        $this->assertSame([], $component->recoveryCodes);
    }

    public function test_trusted_devices_stay_empty_when_the_setup_user_is_resolved_but_not_authenticated(): void
    {
        $user = $this->signIn();
        $this->enableTwoFactorFor($user);
        $this->app['auth']->guard()->logout();
        session()->flush();

        // A pending setup of a user who has two-factor: Guardian::user() is still null.
        Guardian::startPendingTwoFactorSetup($user->fresh(), remember: false);

        // mount() is exercised directly, for the same reason as above.
        $component = new TwoFactorSetup;
        $component->mount();

        $this->assertTrue($component->enabled);
        $this->assertSame([], $component->trustedDevices);
    }
}
