<?php

namespace Datalogix\Guardian\Tests\Feature\Framework\Livewire\Pages;

use Datalogix\Guardian\Actions\EnableTwoFactor;
use Datalogix\Guardian\Actions\Login;
use Datalogix\Guardian\Actions\PrepareTwoFactorSetup;
use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Framework\Livewire\Pages\TwoFactorChallenge;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Group;
use PragmaRX\Google2FA\Google2FA;

#[Group('livewire')]
class TwoFactorChallengeComponentTest extends TestCase
{
    protected function fortresses(): array
    {
        return [Fortress::make()->basic()->twoFactor()];
    }

    protected function createUserWithPendingChallenge(): array
    {
        $user = $this->createUser(['password' => Hash::make('secret123')]);

        $this->actingAs($user);
        session()->put('auth.password_confirmed_at', time());
        $setup = app(PrepareTwoFactorSetup::class)($user);
        $code = app(Google2FA::class)->getCurrentOtp($setup['secret']);
        app(EnableTwoFactor::class)($user, ['code' => $code]);
        $this->app['auth']->guard()->logout();
        session()->flush();

        app(Login::class)(['login' => $user->email, 'password' => 'secret123']);

        return [$user->fresh(), $setup['secret']];
    }

    public function test_it_redirects_to_login_without_a_pending_challenge(): void
    {
        Livewire::test(TwoFactorChallenge::class);

        $this->assertFalse(Guardian::isAuthenticated());
    }

    public function test_it_renders_with_a_pending_challenge(): void
    {
        $this->createUserWithPendingChallenge();

        Livewire::test(TwoFactorChallenge::class)->assertOk();
    }

    public function test_submit_authenticates_with_a_valid_code(): void
    {
        [$user, $secret] = $this->createUserWithPendingChallenge();

        $code = app(Google2FA::class)->getCurrentOtp($secret);

        Livewire::test(TwoFactorChallenge::class)
            ->set('code', $code)
            ->call('submit');

        $this->assertTrue(Guardian::isAuthenticated());
        $this->assertTrue(Guardian::user()->is($user));
    }

    public function test_submit_fails_with_an_invalid_code(): void
    {
        $this->createUserWithPendingChallenge();

        Livewire::test(TwoFactorChallenge::class)
            ->set('code', '000000')
            ->call('submit')
            ->assertHasErrors('code');

        $this->assertFalse(Guardian::isAuthenticated());
    }

    public function test_resend_does_not_throw_for_totp(): void
    {
        $this->createUserWithPendingChallenge();
        Notification::fake();

        Livewire::test(TwoFactorChallenge::class)->call('resend')->assertNoRedirect();

        Notification::assertNothingSent();

    }

    public function test_the_method_the_page_shows_is_read_only_for_the_browser(): void
    {
        $this->createUserWithPendingChallenge();

        $this->expectException(CannotUpdateLockedPropertyException::class);
        $this->expectExceptionMessage('[method]');

        Livewire::test(TwoFactorChallenge::class)->set('method', 'sms');
    }
}
