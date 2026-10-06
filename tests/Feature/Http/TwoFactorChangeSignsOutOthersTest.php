<?php

namespace Datalogix\Guardian\Tests\Feature\Http;

use Datalogix\Guardian\Actions\DisableTwoFactor;
use Datalogix\Guardian\Actions\EnableTwoFactor;
use Datalogix\Guardian\Actions\Login;
use Datalogix\Guardian\Actions\PrepareTwoFactorSetup;
use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Support\TwoFactor\TwoFactorUser;
use Datalogix\Guardian\Tests\Attributes\WithFortresses;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorChangeSignsOutOthersTest extends TestCase
{
    protected function fortresses(): array
    {
        return [Fortress::make()->basic()->twoFactor()];
    }

    protected function withoutTwoFactor(): array
    {
        return [Fortress::make()->basic()];
    }

    protected function user()
    {
        return $this->createUser(['password' => Hash::make('secret123')]);
    }

    /**
     * Signs in like an attacker, with the password and "remember me": returns the session and cookie they keep.
     */
    protected function signInElsewhere($user): array
    {
        app(Login::class)(['login' => $user->email, 'password' => 'secret123'], remember: true);

        return [session()->all(), app('cookie')->queued(Guardian::auth()->getRecallerName())->getValue()];
    }

    protected function asTheOwner($user): void
    {
        $this->newBrowser();
        Guardian::auth()->login($user->fresh());
        session()->put('auth.password_confirmed_at', time());
    }

    protected function enableTwoFactor($user): void
    {
        $setup = app(PrepareTwoFactorSetup::class)($user->fresh());
        app(EnableTwoFactor::class)($user->fresh(), ['code' => app(Google2FA::class)->getCurrentOtp($setup['secret'])]);
    }

    protected function newBrowser(array $session = []): void
    {
        Auth::forgetGuards();
        session()->flush();
        session()->put($session);
    }

    protected function visitAProtectedPage(array $cookies = [])
    {
        Auth::forgetGuards();

        return $this->withCookies($cookies)->get(Guardian::getPasswordConfirmationFeature()->getUrl());
    }

    public function test_a_remember_me_cookie_from_before_enabling_two_factor_signs_in_no_more(): void
    {
        $user = $this->user();
        [, $cookie] = $this->signInElsewhere($user);

        $this->asTheOwner($user);
        $this->enableTwoFactor($user);

        $this->newBrowser();
        $this->visitAProtectedPage([Guardian::auth()->getRecallerName() => $cookie])
            ->assertRedirect(Guardian::getLoginFeature()->getUrl());
        $this->assertGuest();
    }

    public function test_a_session_opened_before_enabling_two_factor_is_signed_out(): void
    {
        $user = $this->user();
        // No request of that session between signing in and the change.
        [$session] = $this->signInElsewhere($user);

        $this->asTheOwner($user);
        $this->enableTwoFactor($user);

        $this->newBrowser($session);
        $this->visitAProtectedPage()->assertRedirect(Guardian::getLoginFeature()->getUrl());
        $this->assertGuest();
    }

    public function test_a_session_opened_before_disabling_two_factor_stays_signed_in(): void
    {
        $user = $this->user();
        $this->asTheOwner($user);
        $this->enableTwoFactor($user);

        $this->newBrowser();
        Guardian::auth()->login($user->fresh());
        $this->visitAProtectedPage()->assertOk();
        $session = session()->all();

        $this->asTheOwner($user);
        app(DisableTwoFactor::class)($user->fresh());

        $this->newBrowser($session);
        $this->visitAProtectedPage()->assertOk();
        $this->visitAProtectedPage()->assertOk();
        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_a_remember_me_cookie_from_before_disabling_two_factor_still_signs_in(): void
    {
        $user = $this->user();
        $this->asTheOwner($user);
        $this->enableTwoFactor($user);
        $token = $user->fresh()->getRememberToken();

        $this->asTheOwner($user);
        app(DisableTwoFactor::class)($user->fresh());

        $this->assertSame($token, $user->fresh()->getRememberToken());
    }

    public function test_a_session_opened_while_two_factor_was_disabled_is_signed_out_once_it_is_enabled_again(): void
    {
        $user = $this->user();
        $this->asTheOwner($user);
        $this->enableTwoFactor($user);
        $this->asTheOwner($user);
        app(DisableTwoFactor::class)($user->fresh());

        [$session] = $this->signInElsewhere($user);
        $this->newBrowser($session);
        $this->visitAProtectedPage()->assertOk();
        $session = session()->all();

        $this->asTheOwner($user);
        $this->enableTwoFactor($user);

        $this->newBrowser($session);
        $this->visitAProtectedPage()->assertRedirect(Guardian::getLoginFeature()->getUrl());
        $this->assertGuest();
    }

    public function test_a_session_that_kept_nothing_yet_is_signed_out_once_two_factor_is_enabled(): void
    {
        $user = $this->user();
        // Signed in before the state was kept, as sessions from an earlier version are.
        [$session] = $this->signInElsewhere($user);
        unset($session['guardian_two_factor_'.Guardian::getGuard()]);

        $this->newBrowser($session);
        $this->visitAProtectedPage()->assertOk();
        $session = session()->all();

        $this->asTheOwner($user);
        $this->enableTwoFactor($user);

        $this->newBrowser($session);
        $this->visitAProtectedPage()->assertRedirect(Guardian::getLoginFeature()->getUrl());
    }

    public function test_the_session_that_enabled_two_factor_stays_signed_in(): void
    {
        $user = $this->user();
        $this->asTheOwner($user);
        $this->visitAProtectedPage()->assertOk();

        $this->enableTwoFactor($user);

        $this->visitAProtectedPage()->assertOk();
        $this->assertAuthenticatedAs($user->fresh());
    }

    #[WithFortresses('withoutTwoFactor')]
    public function test_nothing_is_kept_in_the_session_of_a_fortress_without_two_factor(): void
    {
        $this->asTheOwner($this->user());

        $this->visitAProtectedPage()->assertOk();

        $this->assertEmpty(array_filter(array_keys(session()->all()), fn ($key) => str_starts_with($key, 'guardian_two_factor_')));
    }

    public function test_a_secret_that_cannot_be_read_does_not_sign_the_session_out_by_itself(): void
    {
        $user = $this->user();
        $this->asTheOwner($user);
        $this->enableTwoFactor($user);
        DB::table('users')->where('id', $user->id)->update(['two_factor_secret' => 'not-encrypted-data']);

        // Signed in while the secret was already unreadable: it stays so on every request.
        $this->asTheOwner($user);
        foreach ([1, 2] as $request) {
            $this->app->forgetInstance(TwoFactorUser::class);
            $this->visitAProtectedPage()->assertOk();
        }
    }
}
