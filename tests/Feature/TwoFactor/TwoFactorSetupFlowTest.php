<?php

namespace Datalogix\Guardian\Tests\Feature\TwoFactor;

use Datalogix\Guardian\Actions\DisableTwoFactor;
use Datalogix\Guardian\Actions\EnableTwoFactor;
use Datalogix\Guardian\Actions\PrepareTwoFactorSetup;
use Datalogix\Guardian\Actions\RegenerateTwoFactorRecoveryCodes;
use Datalogix\Guardian\Enums\TwoFactorMethod;
use Datalogix\Guardian\Events\TwoFactorDisabled;
use Datalogix\Guardian\Events\TwoFactorEnabled;
use Datalogix\Guardian\Events\TwoFactorRecoveryCodesRegenerated;
use Datalogix\Guardian\Exceptions\PasswordConfirmationException;
use Datalogix\Guardian\Exceptions\TwoFactorSetupException;
use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Framework\Livewire\Pages\TwoFactorSetup;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Notifications\TwoFactorCodeNotification;
use Datalogix\Guardian\Support\TwoFactor\Totp;
use Datalogix\Guardian\Support\TwoFactor\TwoFactorUser;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorSetupFlowTest extends TestCase
{
    protected function fortresses(): array
    {
        return [Fortress::make()->basic()->twoFactor()];
    }

    protected function confirmPassword(): void
    {
        session()->put('auth.password_confirmed_at', time());
    }

    public function test_enabling_two_factor_requires_a_recently_confirmed_password(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $this->expectException(PasswordConfirmationException::class);
        $this->expectExceptionMessage(PasswordConfirmationException::requiredForEnablingTwoFactor()->getMessage());

        app(PrepareTwoFactorSetup::class)($user);
    }

    public function test_prepare_returns_a_secret_uri_and_qr_code(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);
        $this->confirmPassword();

        $setup = app(PrepareTwoFactorSetup::class)($user);

        $this->assertArrayHasKey('secret', $setup);
        $this->assertArrayHasKey('uri', $setup);
        $this->assertArrayHasKey('qr_svg', $setup);
        $this->assertSame('totp', $setup['method']);
        $this->assertStringContainsString('<svg', $setup['qr_svg']);
    }

    public function test_enable_activates_two_factor_with_a_valid_code(): void
    {
        Event::fake([TwoFactorEnabled::class]);

        $user = $this->createUser();
        $this->actingAs($user);
        $this->confirmPassword();

        $setup = app(PrepareTwoFactorSetup::class)($user);
        $code = app(Google2FA::class)->getCurrentOtp($setup['secret']);

        $recoveryCodes = app(EnableTwoFactor::class)($user, ['code' => $code]);

        $this->assertCount(8, $recoveryCodes);

        $fortress = Guardian::getCurrentOrDefaultFortress();
        $this->assertTrue(app(TwoFactorUser::class)->hasTwoFactorEnabled($user->fresh(), $fortress));

        Event::assertDispatched(TwoFactorEnabled::class);
    }

    public function test_enable_rejects_an_invalid_code(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);
        $this->confirmPassword();

        app(PrepareTwoFactorSetup::class)($user);

        $this->expectException(TwoFactorSetupException::class);
        $this->expectExceptionMessage(TwoFactorSetupException::invalidCode()->getMessage());

        app(EnableTwoFactor::class)($user, ['code' => '000000']);
    }

    public function test_enable_fails_without_a_prior_prepare_call(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);
        $this->confirmPassword();

        $this->expectException(TwoFactorSetupException::class);
        $this->expectExceptionMessage(TwoFactorSetupException::missingPendingSecret()->getMessage());

        app(EnableTwoFactor::class)($user, ['code' => '123456']);
    }

    public function test_disable_requires_a_recently_confirmed_password(): void
    {
        $user = $this->enableTwoFactorFor($this->createUser());
        $this->actingAs($user);

        $this->expectException(PasswordConfirmationException::class);
        $this->expectExceptionMessage(PasswordConfirmationException::requiredForDisablingTwoFactor()->getMessage());

        app(DisableTwoFactor::class)($user);
    }

    public function test_disable_clears_the_secret_and_recovery_codes(): void
    {
        Event::fake([TwoFactorDisabled::class]);

        $user = $this->enableTwoFactorFor($this->createUser());
        $this->actingAs($user);
        $this->confirmPassword();

        app(DisableTwoFactor::class)($user);

        $fortress = Guardian::getCurrentOrDefaultFortress();
        $this->assertFalse(app(TwoFactorUser::class)->hasTwoFactorEnabled($user->fresh(), $fortress));

        Event::assertDispatched(TwoFactorDisabled::class);
    }

    public function test_regenerate_recovery_codes_returns_a_fresh_set(): void
    {
        Event::fake([TwoFactorRecoveryCodesRegenerated::class]);

        $user = $this->enableTwoFactorFor($this->createUser());
        $this->actingAs($user);
        $this->confirmPassword();

        $codes = app(RegenerateTwoFactorRecoveryCodes::class)($user);

        $this->assertCount(8, $codes);

        Event::assertDispatched(TwoFactorRecoveryCodesRegenerated::class);
    }

    public function test_regenerate_recovery_codes_requires_a_recently_confirmed_password(): void
    {
        $user = $this->enableTwoFactorFor($this->createUser());
        $this->actingAs($user);

        $this->expectException(PasswordConfirmationException::class);
        $this->expectExceptionMessage(PasswordConfirmationException::requiredForRegeneratingRecoveryCodes()->getMessage());

        app(RegenerateTwoFactorRecoveryCodes::class)($user);
    }

    protected function enableTwoFactorFor($user)
    {
        $this->actingAs($user);
        $this->confirmPassword();

        $setup = app(PrepareTwoFactorSetup::class)($user);
        $code = app(Google2FA::class)->getCurrentOtp($setup['secret']);

        app(EnableTwoFactor::class)($user, ['code' => $code]);

        $this->app['auth']->guard()->logout();
        session()->flush();

        return $user->fresh();
    }

    #[Group('livewire')]
    public function test_users_who_cannot_access_the_fortress_are_forbidden_from_mounting_setup(): void
    {
        $user = $this->createUser(['can_access' => false]);
        $this->actingAs($user);
        session()->put('auth.password_confirmed_at', time());

        Livewire::test(TwoFactorSetup::class)->assertForbidden();
    }

    public function test_enable_itself_requires_a_recently_confirmed_password(): void
    {
        // Exercises EnableTwoFactor's own password-confirmation guard directly,
        // independent of PrepareTwoFactorSetup (which normally runs first and
        // would already have thrown for the same reason).
        $user = $this->createUser();
        $this->actingAs($user);

        $this->expectException(PasswordConfirmationException::class);
        $this->expectExceptionMessage(PasswordConfirmationException::requiredForEnablingTwoFactor()->getMessage());

        app(EnableTwoFactor::class)($user, ['code' => '123456']);
    }

    public function test_it_throws_when_the_totp_account_label_cannot_be_resolved(): void
    {
        // Totp::resolveAccountLabel() is only ever called here with its
        // default $default = 'user' argument, which is never blank on its
        // own — this failure mode can only come from a Totp implementation
        // swap (e.g. a custom binding), simulated here via a partial mock.
        $totp = Mockery::mock(Totp::class)->makePartial();
        $totp->shouldReceive('resolveAccountLabel')->andReturn('');
        $this->app->instance(Totp::class, $totp);

        $user = $this->createUser();
        $this->actingAs($user);
        session()->put('auth.password_confirmed_at', time());

        $this->expectException(TwoFactorSetupException::class);
        $this->expectExceptionMessage(TwoFactorSetupException::invalidAccountLabel()->getMessage());

        app(PrepareTwoFactorSetup::class)($user);
    }

    public function test_preparing_setup_for_a_delivery_based_method_sends_a_code(): void
    {
        Notification::fake();

        $user = $this->createUser();
        $this->actingAs($user);
        session()->put('auth.password_confirmed_at', time());

        app(PrepareTwoFactorSetup::class)($user, TwoFactorMethod::Email);

        Notification::assertSentOnDemand(TwoFactorCodeNotification::class);
    }
}
