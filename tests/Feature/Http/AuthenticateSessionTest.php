<?php

namespace Datalogix\Guardian\Tests\Feature\Http;

use BadMethodCallException;
use Datalogix\Guardian\Actions\Login;
use Datalogix\Guardian\Actions\ResetPassword;
use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Http\Middleware\AuthenticateSession;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Auth\GenericUser;
use Illuminate\Auth\SessionGuard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

/**
 * Resetting a stolen password signs out whoever signed in with it.
 */
class AuthenticateSessionTest extends TestCase
{
    protected function signedInUser()
    {
        $user = $this->createUser(['password' => Hash::make('old-password')]);

        app(Login::class)(['login' => $user->email, 'password' => 'old-password']);

        return $user;
    }

    protected function resetThePasswordOf($user): void
    {
        app(ResetPassword::class)([
            'token' => Password::broker()->createToken($user),
            'login' => $user->email,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);
    }

    /**
     * A later request of the same browser, which reads the user from the session again.
     */
    protected function visitAProtectedPage()
    {
        Auth::forgetGuards();

        return $this->get(Guardian::getPasswordConfirmationFeature()->getUrl());
    }

    public function test_the_session_stays_signed_in_while_the_password_does_not_change(): void
    {
        $this->signedInUser();

        $this->visitAProtectedPage()->assertOk();
        $this->visitAProtectedPage()->assertOk();
    }

    public function test_resetting_the_password_signs_out_a_session_opened_with_the_old_one(): void
    {
        $user = $this->signedInUser();
        $this->visitAProtectedPage()->assertOk();

        $this->resetThePasswordOf($user);

        $this->visitAProtectedPage()->assertRedirect(Guardian::getLoginFeature()->getUrl());
        $this->assertGuest();
    }

    public function test_a_password_reset_right_after_signing_in_still_signs_the_session_out(): void
    {
        // No request of the session between signing in and the reset.
        $user = $this->signedInUser();

        $this->resetThePasswordOf($user);

        $this->visitAProtectedPage()->assertRedirect(Guardian::getLoginFeature()->getUrl());
    }

    public function test_nothing_is_stored_for_a_user_without_a_password(): void
    {
        AuthenticateSession::storePasswordHash(new GenericUser(['id' => 1, 'password' => '']));

        $this->assertFalse(session()->has('password_hash_'.Guardian::getGuard()));
    }

    public function test_the_password_hash_itself_is_stored_by_a_guard_of_laravel_11_and_12(): void
    {
        // Their SessionGuard has no hashPasswordForCookie() yet.
        Auth::extend('legacy', fn ($app, $name, array $config) => new class($name, Auth::createUserProvider($config['provider']), $app['session.store']) extends SessionGuard
        {
            public function hashPasswordForCookie($passwordHash)
            {
                throw new BadMethodCallException;
            }
        });
        config(['auth.guards.legacy' => ['driver' => 'legacy', 'provider' => 'users']]);
        Guardian::setCurrentFortress(Fortress::make()->basic('legacy')->guard('legacy'));

        $user = $this->createUser();
        AuthenticateSession::storePasswordHash($user);

        $this->assertSame($user->getAuthPassword(), session('password_hash_legacy'));
    }
}
