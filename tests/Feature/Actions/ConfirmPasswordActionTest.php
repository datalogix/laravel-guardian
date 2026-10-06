<?php

namespace Datalogix\Guardian\Tests\Feature\Actions;

use Datalogix\Guardian\Actions\ConfirmPassword;
use Datalogix\Guardian\Exceptions\PasswordConfirmationException;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Tests\Fixtures\RecordingTimebox;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Timebox;
use Illuminate\Validation\Rules\Password;

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

    public function test_a_password_from_before_stricter_rules_is_still_confirmed(): void
    {
        $user = $this->createUser(['password' => Hash::make('secret')]);
        $this->actingAs($user);
        Password::defaults(fn () => Password::min(12)->symbols());

        try {
            $data = Validator::validate(['password' => 'secret'], ConfirmPassword::rules());
            app(ConfirmPassword::class)($data);
        } finally {
            Password::defaults(fn () => Password::min(8));
        }

        $this->assertNotNull(session('auth.password_confirmed_at'));
    }

    public function test_only_a_correct_password_answers_before_the_timebox_ends(): void
    {
        $this->app->instance(Timebox::class, $timebox = new RecordingTimebox);
        $this->actingAs($this->createUser(['password' => Hash::make('secret')]));

        try {
            app(ConfirmPassword::class)(['password' => 'wrong']);
        } catch (PasswordConfirmationException) {
            // expected
        }

        app(ConfirmPassword::class)(['password' => 'secret']);

        $this->assertSame([false, true], $timebox->returnedEarly);
    }
}
