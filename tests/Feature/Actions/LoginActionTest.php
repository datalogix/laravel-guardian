<?php

namespace Datalogix\Guardian\Tests\Feature\Actions;

use Datalogix\Guardian\Actions\Login;
use Datalogix\Guardian\Enums\AuthFlowResult;
use Datalogix\Guardian\Exceptions\LoginException;
use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Tests\Fixtures\BareGuard;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Auth\Events\Attempting;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Validated;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class LoginActionTest extends TestCase
{
    public function test_valid_credentials_authenticate_the_user(): void
    {
        $user = $this->createUser(['password' => Hash::make('correct-password')]);

        $result = app(Login::class)(['login' => $user->email, 'password' => 'correct-password']);

        $this->assertSame(AuthFlowResult::Authenticated, $result);
        $this->assertTrue(Guardian::isAuthenticated());
        $this->assertTrue(Guardian::user()->is($user));
    }

    public function test_invalid_password_throws_login_exception(): void
    {
        $user = $this->createUser(['password' => Hash::make('correct-password')]);

        $this->expectException(LoginException::class);
        $this->expectExceptionMessage(LoginException::invalid()->getMessage());

        app(Login::class)(['login' => $user->email, 'password' => 'wrong-password']);
    }

    public function test_unknown_user_throws_login_exception(): void
    {
        $this->expectException(LoginException::class);
        $this->expectExceptionMessage(LoginException::invalid()->getMessage());

        app(Login::class)(['login' => 'nobody@example.com', 'password' => 'whatever']);
    }

    public function test_user_that_cannot_access_the_fortress_is_rejected(): void
    {
        $user = $this->createUser(['password' => Hash::make('secret'), 'can_access' => false]);

        $this->expectException(LoginException::class);
        $this->expectExceptionMessage(LoginException::cannotAccess()->getMessage());

        app(Login::class)(['login' => $user->email, 'password' => 'secret']);
    }

    public function test_dispatches_auth_lifecycle_events(): void
    {
        Event::fake([Attempting::class, Validated::class]);

        $user = $this->createUser(['password' => Hash::make('secret')]);

        app(Login::class)(['login' => $user->email, 'password' => 'secret']);

        Event::assertDispatched(Attempting::class);
        Event::assertDispatched(Validated::class);
    }

    public function test_dispatches_failed_event_on_invalid_credentials(): void
    {
        Event::fake([Failed::class]);

        $user = $this->createUser(['password' => Hash::make('secret')]);

        try {
            app(Login::class)(['login' => $user->email, 'password' => 'wrong']);
        } catch (LoginException) {
            // expected
        }

        Event::assertDispatched(Failed::class);
    }

    public function test_login_is_rate_limited_after_max_attempts(): void
    {
        $user = $this->createUser(['password' => Hash::make('secret')]);

        for ($i = 0; $i < 5; $i++) {
            try {
                app(Login::class)(['login' => $user->email, 'password' => 'wrong']);
            } catch (LoginException) {
                // expected until the limit is hit
            }
        }

        $this->expectException(LoginException::class);

        try {
            app(Login::class)(['login' => $user->email, 'password' => 'wrong']);
        } catch (LoginException $exception) {
            $this->assertStringContainsString('seconds', $exception->errors()['login'][0]);

            throw $exception;
        }
    }

    public function test_successful_login_clears_the_rate_limiter(): void
    {
        $user = $this->createUser(['password' => Hash::make('secret')]);

        try {
            app(Login::class)(['login' => $user->email, 'password' => 'wrong']);
        } catch (LoginException) {
            // expected
        }

        app(Login::class)(['login' => $user->email, 'password' => 'secret']);

        $throttleKey = sha1(implode('|', [Login::class, request()->ip(), Str::lower($user->email)]));

        $this->assertSame(0, RateLimiter::attempts($throttleKey));
    }

    public function test_rules_use_the_identifier_key_validation(): void
    {
        $rules = Login::rules();

        $this->assertArrayHasKey('login', $rules);
        $this->assertArrayHasKey('password', $rules);
    }

    public function test_login_fails_gracefully_when_the_guard_cannot_resolve_a_provider(): void
    {
        Auth::extend('bare', fn () => new BareGuard);
        config(['auth.guards.bare' => ['driver' => 'bare']]);

        $fortress = Fortress::make()->basic('bare-fortress')->guard('bare');
        Guardian::setCurrentFortress($fortress);

        // retrieveUser() turns the exception into "no match".
        $this->expectException(LoginException::class);
        $this->expectExceptionMessage(LoginException::invalid()->getMessage());

        app(Login::class)(['login' => 'someone@example.com', 'password' => 'whatever']);
    }
}
