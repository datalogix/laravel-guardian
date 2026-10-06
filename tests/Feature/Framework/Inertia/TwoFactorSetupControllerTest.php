<?php

namespace Datalogix\Guardian\Tests\Feature\Framework\Inertia;

use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Support\TwoFactor\TrustedDevices;
use Datalogix\Guardian\Support\TwoFactor\TwoFactorUser;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Group;
use PragmaRX\Google2FA\Google2FA;

#[Group('inertia')]
class TwoFactorSetupControllerTest extends InertiaTestCase
{
    protected function fortresses(): array
    {
        return [Fortress::make()->inertia()->basic()->twoFactor(rememberOnDevice: true)];
    }

    protected function signIn(bool $confirmedPassword = true)
    {
        $user = $this->createUser(['password' => Hash::make('secret123')]);

        $this->actingAs($user);

        if ($confirmedPassword) {
            $this->withSession(['auth.password_confirmed_at' => time()]);
        }

        return $user;
    }

    protected function enableTwoFactor(): array
    {
        $this->inertiaPost('/two-factor/setup/prepare');
        $secret = $this->inertiaGet('/two-factor/setup')->json('props.secret');

        $this->inertiaPost('/two-factor/setup/enable', ['code' => app(Google2FA::class)->getCurrentOtp($secret)]);

        return [$secret];
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->inertiaGet('/two-factor/setup')->assertRedirect();
    }

    public function test_it_renders_the_disabled_state(): void
    {
        $this->signIn();

        $this->assertPage($this->inertiaGet('/two-factor/setup'), 'Guardian/TwoFactorSetup', [
            'enabled' => false,
            'secret' => null,
            'qrSvg' => null,
            'recoveryCodes' => [],
            'trustedDevices' => [],
            'method' => 'totp',
            'endpoints.prepare' => url('/two-factor/setup/prepare'),
            'endpoints.enable' => url('/two-factor/setup/enable'),
            'endpoints.disable' => url('/two-factor/setup'),
            'endpoints.recovery-codes' => url('/two-factor/setup/recovery-codes'),
            'endpoints.continue' => url('/two-factor/setup/continue'),
        ]);
    }

    public function test_each_trusted_device_comes_with_the_url_that_revokes_it(): void
    {
        $user = $this->signIn();
        $this->enableTwoFactor();
        $issued = (new TrustedDevices)->issue(Guardian::getCurrentOrDefaultFortress(), $user->fresh(), 30);

        $devices = $this->inertiaGet('/two-factor/setup')->json('props.trustedDevices');

        $this->assertCount(1, $devices);
        $this->assertSame($issued['id'], $devices[0]['id']);
        $this->assertSame(url("/two-factor/setup/trusted-devices/{$issued['id']}"), $devices[0]['revokeUrl']);
    }

    public function test_prepare_generates_a_pending_secret_shown_on_the_next_visit(): void
    {
        $this->signIn();

        $this->inertiaPost('/two-factor/setup/prepare')->assertRedirect();

        $page = $this->inertiaGet('/two-factor/setup');

        $this->assertPage($page, 'Guardian/TwoFactorSetup', ['enabled' => false, 'method' => 'totp']);
        $this->assertNotEmpty($page->json('props.secret'));
        $this->assertStringStartsWith('otpauth://totp/', $page->json('props.uri'));
        $this->assertStringContainsString('<svg', $page->json('props.qrSvg'));
    }

    public function test_enable_activates_two_factor_and_shows_the_recovery_codes_once(): void
    {
        $user = $this->signIn();

        $this->enableTwoFactor();

        $this->assertTrue(app(TwoFactorUser::class)
            ->hasTwoFactorEnabled($user->fresh(), Guardian::getCurrentOrDefaultFortress()));

        $page = $this->inertiaGet('/two-factor/setup');

        $this->assertPage($page, 'Guardian/TwoFactorSetup', [
            'enabled' => true,
            'secret' => null,
            'canManageRecoveryCodes' => true,
            'recoveryCodesCount' => 8,
            'awaitingContinueAfterSetup' => false,
        ]);
        $this->assertCount(8, $page->json('props.recoveryCodes'));
    }

    public function test_enable_rejects_an_invalid_code(): void
    {
        $this->signIn();

        $this->inertiaPost('/two-factor/setup/prepare');
        $this->inertiaPost('/two-factor/setup/enable', ['code' => '000000'])->assertSessionHasErrors();

        $this->assertPage($this->inertiaGet('/two-factor/setup'), 'Guardian/TwoFactorSetup', ['enabled' => false]);
    }

    public function test_enable_validates_the_code_format(): void
    {
        $this->signIn();

        $this->inertiaPost('/two-factor/setup/enable', ['code' => 'abc'])->assertSessionHasErrors('code');
    }

    public function test_disable_turns_two_factor_off(): void
    {
        $this->signIn();
        $this->enableTwoFactor();

        $this->inertiaDelete('/two-factor/setup')->assertRedirect();

        $this->assertPage($this->inertiaGet('/two-factor/setup'), 'Guardian/TwoFactorSetup', ['enabled' => false]);
    }

    public function test_regenerating_recovery_codes_replaces_them(): void
    {
        $this->signIn();
        $this->enableTwoFactor();
        $original = $this->inertiaGet('/two-factor/setup')->json('props.recoveryCodes');

        $this->inertiaPost('/two-factor/setup/recovery-codes')->assertRedirect();

        $regenerated = $this->inertiaGet('/two-factor/setup')->json('props.recoveryCodes');

        $this->assertCount(8, $regenerated);
        $this->assertNotSame($original, $regenerated);
    }

    public function test_it_asks_for_the_password_again_when_the_confirmation_is_stale(): void
    {
        $this->signIn(confirmedPassword: false);

        $this->inertiaPost('/two-factor/setup/prepare')->assertRedirect(url('/confirm-password'));

        $this->assertSame(url('/two-factor/setup'), session('url.intended'));
    }

    public function test_revoking_a_trusted_device_goes_back(): void
    {
        $this->signIn();

        $this->from('/two-factor/setup')->inertiaDelete('/two-factor/setup/trusted-devices/999')->assertRedirect('/two-factor/setup');
        $this->from('/two-factor/setup')->inertiaDelete('/two-factor/setup/trusted-devices')->assertRedirect('/two-factor/setup');
    }

    public function test_the_trusted_device_id_must_be_numeric(): void
    {
        $this->signIn();

        $this->inertiaDelete('/two-factor/setup/trusted-devices/not-a-number')->assertNotFound();
    }
}
