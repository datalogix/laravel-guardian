<?php

namespace Datalogix\Guardian\Tests\Feature\Actions;

use Datalogix\Guardian\Actions\ConfirmPassword;
use Datalogix\Guardian\Exceptions\PasswordConfirmationException;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Support\Facades\Hash;

class ConfirmPasswordActionTest extends TestCase
{
    public function test_it_confirms_a_correct_password(): void
    {
        $user = $this->createUser(['password' => Hash::make('secret')]);
        $this->actingAs($user);

        app(ConfirmPassword::class)(['password' => 'secret']);

        $this->assertNotNull(session('auth.password_confirmed_at'));
    }

    public function test_it_rejects_an_incorrect_password(): void
    {
        $user = $this->createUser(['password' => Hash::make('secret')]);
        $this->actingAs($user);

        $this->expectException(PasswordConfirmationException::class);
        $this->expectExceptionMessage(PasswordConfirmationException::invalid()->getMessage());

        app(ConfirmPassword::class)(['password' => 'wrong']);
    }

    public function test_it_throws_when_there_is_no_authenticated_user(): void
    {
        $this->expectException(PasswordConfirmationException::class);
        $this->expectExceptionMessage(PasswordConfirmationException::invalid()->getMessage());

        app(ConfirmPassword::class)(['password' => 'secret']);
    }

    public function test_it_is_rate_limited(): void
    {
        $user = $this->createUser(['password' => Hash::make('secret')]);
        $this->actingAs($user);

        $maxAttempts = Guardian::getPasswordConfirmationFeature()->getMaxAttempts();

        for ($i = 0; $i < $maxAttempts; $i++) {
            try {
                app(ConfirmPassword::class)(['password' => 'wrong']);
            } catch (PasswordConfirmationException) {
                // expected until the limit is hit
            }
        }

        $this->expectException(PasswordConfirmationException::class);

        try {
            app(ConfirmPassword::class)(['password' => 'wrong']);
        } catch (PasswordConfirmationException $exception) {
            $this->assertStringContainsString('seconds', $exception->errors()['password'][0]);

            throw $exception;
        }
    }
}
