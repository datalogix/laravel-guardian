<?php

namespace Datalogix\Guardian\Tests\Feature\Framework\Inertia;

use Datalogix\Guardian\Actions\ForgotPassword;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\Group;

#[Group('inertia')]
class PasswordControllersTest extends InertiaTestCase
{
    public function test_forgot_password_renders_the_page(): void
    {
        $this->assertPage($this->inertiaGet('/forgot-password'), 'Guardian/ForgotPassword', [
            'identifierKey' => 'email',
            'loginUrl' => url('/login'),
        ]);
    }

    public function test_forgot_password_sends_the_link_and_flashes_the_status(): void
    {
        Notification::fake();
        $user = $this->createUser(['email' => 'forgot@example.com']);

        $this->inertiaPost('/forgot-password', ['login' => 'forgot@example.com'])
            ->assertRedirect(url('/forgot-password'))
            ->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_forgot_password_gives_the_same_answer_for_an_unknown_login(): void
    {
        Notification::fake();
        $this->createUser(['email' => 'known@example.com']);

        $this->inertiaPost('/forgot-password', ['login' => 'known@example.com'])->assertRedirect(url('/forgot-password'));
        $known = session('status');

        session()->forget('status');

        $this->inertiaPost('/forgot-password', ['login' => 'nobody@example.com'])->assertRedirect(url('/forgot-password'));

        $this->assertSame(__(ForgotPassword::GENERIC_STATUS), $known);
        $this->assertSame($known, session('status'));
    }

    public function test_forgot_password_validates_the_identifier(): void
    {
        $this->inertiaPost('/forgot-password', ['login' => 'nope'])->assertSessionHasErrors('login');
    }

    public function test_reset_password_prefills_the_token_and_login(): void
    {
        $user = $this->createUser();
        $token = Password::broker()->createToken($user);

        $url = URL::signedRoute('auth.password.reset', ['token' => $token, 'login' => $user->email]);

        $this->assertPage($this->inertiaGet($url), 'Guardian/ResetPassword', [
            'token' => $token,
            'login' => $user->email,
            'endpoints.submit' => url('/reset-password'),
        ]);
    }

    public function test_reset_password_page_requires_a_signed_url(): void
    {
        $this->inertiaGet('/reset-password/some-token')->assertForbidden();
    }

    public function test_reset_password_changes_the_password_with_a_valid_token(): void
    {
        $user = $this->createUser(['password' => Hash::make('old-password')]);
        $token = Password::broker()->createToken($user);

        $this->inertiaPost('/reset-password', [
            'token' => $token,
            'login' => $user->email,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertRedirect(url('/login'));

        $this->assertTrue(Hash::check('NewPassword123!', $user->fresh()->password));
    }

    public function test_reset_password_fails_for_an_invalid_token(): void
    {
        $user = $this->createUser();

        $this->inertiaPost('/reset-password', [
            'token' => 'invalid',
            'login' => $user->email,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertSessionHasErrors('login');
    }

    public function test_confirm_password_requires_authentication(): void
    {
        $this->inertiaGet('/confirm-password')->assertRedirect();
    }

    public function test_confirm_password_renders_and_stores_the_confirmation(): void
    {
        $this->actingAs($this->createUser());

        $this->assertPage($this->inertiaGet('/confirm-password'), 'Guardian/ConfirmPassword');

        $this->inertiaPost('/confirm-password', ['password' => 'password'])->assertRedirect();

        $this->assertNotNull(session('auth.password_confirmed_at'));
    }

    public function test_confirm_password_rejects_a_wrong_password(): void
    {
        $this->actingAs($this->createUser());

        $this->inertiaPost('/confirm-password', ['password' => 'wrong-password'])->assertSessionHasErrors('password');

        $this->assertNull(session('auth.password_confirmed_at'));
    }
}
