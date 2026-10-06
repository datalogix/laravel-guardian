<?php

namespace Datalogix\Guardian\Tests\Feature\Actions;

use Datalogix\Guardian\Actions\ConfirmPassword;
use Datalogix\Guardian\Actions\Login;
use Datalogix\Guardian\Exceptions\LoginException;
use Datalogix\Guardian\Exceptions\PasswordConfirmationException;
use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class RateLimitsPerFortressTest extends TestCase
{
    protected function fortresses(): array
    {
        return [Fortress::make()->basic(), Fortress::make()->admin()];
    }

    protected function inFortress(string $id): void
    {
        Guardian::setCurrentFortress(Guardian::getFortress($id));
    }

    protected function error(callable $attempt): ?ValidationException
    {
        try {
            $attempt();

            return null;
        } catch (ValidationException $exception) {
            return $exception;
        }
    }

    public function test_password_confirmations_are_limited_per_fortress(): void
    {
        $this->actingAs($this->createUser(['password' => Hash::make('secret')]));
        $wrong = fn () => app(ConfirmPassword::class)(['password' => 'wrong']);

        $this->inFortress('default');
        for ($i = 0; $i < Guardian::getPasswordConfirmationFeature()->getMaxAttempts(); $i++) {
            $this->error($wrong);
        }
        $this->assertStringContainsString('Too many attempts', $this->error($wrong)->getMessage());

        // User 1 of the admin fortress has attempts of their own.
        $this->inFortress('admin');
        $this->assertSame(PasswordConfirmationException::invalid()->getMessage(), $this->error($wrong)->getMessage());
    }

    public function test_sign_in_attempts_are_limited_per_fortress(): void
    {
        $wrong = fn () => app(Login::class)(['login' => 'someone@example.com', 'password' => 'wrong']);

        $this->inFortress('default');
        for ($i = 0; $i < Guardian::getLoginFeature()->getMaxAttempts(); $i++) {
            $this->error($wrong);
        }
        $this->assertStringContainsString('Too many login attempts', $this->error($wrong)->getMessage());

        // The same login at the admin fortress is another account.
        $this->inFortress('admin');
        $this->assertSame(LoginException::invalid()->getMessage(), $this->error($wrong)->getMessage());
    }
}
