<?php

namespace Datalogix\Guardian\Tests\Feature\Actions;

use Datalogix\Guardian\Actions\ResetPassword;
use Datalogix\Guardian\Exceptions\ResetPasswordException;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

class ResetPasswordActionTest extends TestCase
{
    public function test_it_resets_the_password_with_a_valid_token(): void
    {
        Event::fake([PasswordReset::class]);

        $user = $this->createUser(['password' => Hash::make('old-password')]);
        $token = Password::broker()->createToken($user);

        $status = app(ResetPassword::class)([
            'token' => $token,
            'login' => $user->email,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $this->assertSame(Password::PASSWORD_RESET, $status);
        $this->assertTrue(Hash::check('NewPassword123!', $user->fresh()->password));
        Event::assertDispatched(PasswordReset::class);
    }

    public function test_it_rejects_an_invalid_token(): void
    {
        $user = $this->createUser();

        $this->expectException(ResetPasswordException::class);
        $this->expectExceptionMessage(ResetPasswordException::forStatus(Password::INVALID_TOKEN)->getMessage());

        app(ResetPassword::class)([
            'token' => 'invalid-token',
            'login' => $user->email,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);
    }

    public function test_it_rejects_users_who_cannot_access_the_fortress(): void
    {
        $user = $this->createUser(['can_access' => false]);
        $token = Password::broker()->createToken($user);

        $this->expectException(ResetPasswordException::class);
        $this->expectExceptionMessage(ResetPasswordException::forStatus(Password::INVALID_USER)->getMessage());

        app(ResetPassword::class)([
            'token' => $token,
            'login' => $user->email,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);
    }

    public function test_rules_require_token_and_matching_password_confirmation(): void
    {
        $rules = ResetPassword::rules();

        $this->assertArrayHasKey('token', $rules);
        $this->assertArrayHasKey('login', $rules);
        $this->assertArrayHasKey('password', $rules);
    }
}
