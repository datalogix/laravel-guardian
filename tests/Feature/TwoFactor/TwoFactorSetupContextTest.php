<?php

namespace Datalogix\Guardian\Tests\Feature\TwoFactor;

use Datalogix\Guardian\Actions\EnableTwoFactor;
use Datalogix\Guardian\Actions\PrepareTwoFactorSetup;
use Datalogix\Guardian\Enums\TwoFactorMethod;
use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Support\TwoFactor\TwoFactorSetupContext;
use Datalogix\Guardian\Support\TwoFactor\TwoFactorUser;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use PragmaRX\Google2FA\Google2FA;
use Symfony\Component\HttpKernel\Exception\HttpException;

class TwoFactorSetupContextTest extends TestCase
{
    protected function fortresses(): array
    {
        return [Fortress::make()->basic()->twoFactor()];
    }

    protected function context(): TwoFactorSetupContext
    {
        return app(TwoFactorSetupContext::class);
    }

    protected function signInWithConfirmedPassword()
    {
        $user = $this->createUser();

        $this->actingAs($user);
        session()->put('auth.password_confirmed_at', time());

        return $user;
    }

    protected function enableTwoFactor($user): string
    {
        $setup = app(PrepareTwoFactorSetup::class)($user);

        app(EnableTwoFactor::class)($user, ['code' => app(Google2FA::class)->getCurrentOtp($setup['secret'])]);

        return $setup['secret'];
    }

    public function test_the_user_is_the_authenticated_one(): void
    {
        $user = $this->signInWithConfirmedPassword();

        $this->assertTrue($this->context()->user()->is($user));
    }

    public function test_there_is_no_user_for_a_guest(): void
    {
        $this->assertNull($this->context()->user());
        $this->assertNull($this->context()->authorizedUser());
    }

    public function test_an_authorized_user_must_be_able_to_access_the_fortress(): void
    {
        $user = $this->createUser(['can_access' => false]);
        $this->actingAs($user);

        try {
            $this->context()->authorizedUser();
            $this->fail('A user who cannot access the fortress was authorized.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_requiring_authentication_ignores_a_user_that_is_only_pending(): void
    {
        $this->assertNull($this->context()->authorizedUser(requireAuthenticated: true));

        $user = $this->signInWithConfirmedPassword();

        $this->assertTrue($this->context()->authorizedUser(requireAuthenticated: true)->is($user));
    }

    public function test_the_method_follows_the_configuration(): void
    {
        $this->assertSame(TwoFactorMethod::Totp, $this->context()->method());
    }

    public function test_the_summary_of_a_user_without_two_factor(): void
    {
        $user = $this->signInWithConfirmedPassword();

        $this->assertSame([
            'enabled' => false,
            'secretUnreadable' => false,
            'canManageRecoveryCodes' => false,
            'recoveryCodesCount' => 0,
            'recoveryCodes' => [],
            'trustedDevices' => [],
        ], $this->context()->summary($user));
    }

    public function test_the_summary_of_a_user_with_two_factor_counts_the_recovery_codes(): void
    {
        $user = $this->signInWithConfirmedPassword();
        $this->enableTwoFactor($user);

        $summary = $this->context()->summary($user->fresh());

        $this->assertTrue($summary['enabled']);
        $this->assertFalse($summary['secretUnreadable']);
        $this->assertTrue($summary['canManageRecoveryCodes']);
        $this->assertSame(8, $summary['recoveryCodesCount']);
        $this->assertIsArray($summary['recoveryCodes']);
    }

    public function test_the_summary_flags_a_secret_that_cannot_be_decrypted(): void
    {
        $user = $this->signInWithConfirmedPassword();
        $this->enableTwoFactor($user);

        DB::table('users')->where('id', $user->id)->update(['two_factor_secret' => 'not-encrypted-data']);
        $this->app->forgetInstance(TwoFactorUser::class);

        $summary = $this->context()->summary($user->fresh());

        $this->assertTrue($summary['enabled']);
        $this->assertTrue($summary['secretUnreadable']);
        $this->assertSame([], $summary['recoveryCodes']);
    }

    public function test_there_is_no_pending_setup_until_one_is_prepared(): void
    {
        $user = $this->signInWithConfirmedPassword();

        $this->assertNull($this->context()->pendingSetup($user));

        $setup = app(PrepareTwoFactorSetup::class)($user);
        $pending = $this->context()->pendingSetup($user);

        $this->assertSame(TwoFactorMethod::Totp, $pending['method']);
        $this->assertSame($setup['secret'], $pending['secret']);
        $this->assertStringStartsWith('otpauth://totp/', $pending['uri']);
        $this->assertStringContainsString('<svg', $pending['qrSvg']);
    }
}
