<?php

namespace Datalogix\Guardian\Tests\Feature\TwoFactor;

use Datalogix\Guardian\Actions\Login;
use Datalogix\Guardian\Enums\AuthFlowResult;
use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class TwoFactorRequiredSetupLoginTest extends TestCase
{
    protected function fortresses(): array
    {
        return [Fortress::make()->basic()->twoFactor(requireSetupOnLogin: true)];
    }

    public function test_login_requires_two_factor_setup_when_it_has_not_been_enabled_yet(): void
    {
        $user = $this->createUser(['password' => Hash::make('secret123')]);

        $result = app(Login::class)(['login' => $user->email, 'password' => 'secret123']);

        $this->assertSame(AuthFlowResult::SetupRequired, $result);
        $this->assertFalse(Guardian::isAuthenticated());
        $this->assertTrue(Guardian::hasPendingTwoFactorSetup());
    }

    public function test_login_reports_a_friendly_error_when_the_stored_secret_cannot_be_decrypted(): void
    {
        $user = $this->createUser(['password' => Hash::make('secret123')]);
        DB::table('users')->where('id', $user->id)->update(['two_factor_secret' => 'not-encrypted-data']);

        $this->expectException(ValidationException::class);

        try {
            app(Login::class)(['login' => $user->email, 'password' => 'secret123']);
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('could not be verified', $exception->errors()['login'][0]);

            throw $exception;
        }
    }
}
