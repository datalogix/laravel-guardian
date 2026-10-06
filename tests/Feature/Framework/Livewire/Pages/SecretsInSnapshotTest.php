<?php

namespace Datalogix\Guardian\Tests\Feature\Framework\Livewire\Pages;

use Closure;
use Datalogix\Guardian\Actions\EnableTwoFactor;
use Datalogix\Guardian\Actions\PrepareTwoFactorSetup;
use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Framework\Livewire\Pages\ConfirmPassword;
use Datalogix\Guardian\Framework\Livewire\Pages\Login;
use Datalogix\Guardian\Framework\Livewire\Pages\ResetPassword;
use Datalogix\Guardian\Framework\Livewire\Pages\SignUp;
use Datalogix\Guardian\Framework\Livewire\Pages\TwoFactorChallenge;
use Datalogix\Guardian\Framework\Livewire\Pages\TwoFactorSetup;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Tests\TestCase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PragmaRX\Google2FA\Google2FA;

/**
 * Livewire sends public properties back to the browser: a secret must not outlive the action.
 */
#[Group('livewire')]
class SecretsInSnapshotTest extends TestCase
{
    protected const SECRET = 'MySecretPassw0rd!';

    protected const CODE = '987654';

    protected function fortresses(): array
    {
        // With terms, so a sign-up without accepting them fails.
        return [Fortress::make()->product()->default()->signUp(termsUrl: 'https://example.com/terms')->twoFactor()];
    }

    protected function userWithTwoFactor()
    {
        $user = $this->createUser();
        $this->actingAs($user);
        session()->put('auth.password_confirmed_at', time());

        $setup = app(PrepareTwoFactorSetup::class)($user);
        app(EnableTwoFactor::class)($user, ['code' => app(Google2FA::class)->getCurrentOtp($setup['secret'])]);

        return $user->fresh();
    }

    public static function failedAttempts(): array
    {
        return [
            'signing in' => [Login::class, fn () => null, [
                'login' => 'someone@example.com',
                'password' => self::SECRET,
            ], 'submit'],
            'signing up' => [SignUp::class, fn () => null, [
                'name' => 'New User',
                'login' => 'new@example.com',
                'password' => self::SECRET,
                'password_confirmation' => self::SECRET,
                'terms' => false,
            ], 'submit'],
            'resetting the password' => [ResetPassword::class, fn () => null, [
                'password' => self::SECRET,
                'password_confirmation' => self::SECRET,
            ], 'submit', ['token' => 'not-a-valid-token', 'login' => 'someone@example.com']],
            'confirming the password' => [ConfirmPassword::class, function (self $test) {
                $test->actingAs($test->createUser());
            }, ['password' => self::SECRET], 'submit'],
            'answering the two-factor challenge' => [TwoFactorChallenge::class, function (self $test) {
                $user = $test->userWithTwoFactor();
                $test->app['auth']->guard()->logout();
                Guardian::startTwoFactorChallenge($user);
            }, ['code' => self::CODE], 'submit'],
            'enabling two-factor authentication' => [TwoFactorSetup::class, function (self $test) {
                $test->actingAs($test->createUser());
                session()->put('auth.password_confirmed_at', time());
            }, ['code' => self::CODE], 'enable'],
        ];
    }

    #[DataProvider('failedAttempts')]
    public function test_a_failed_attempt_does_not_send_the_secret_back_to_the_browser(string $page, Closure $arrange, array $fields, string $action, array $mount = []): void
    {
        $arrange($this);

        $component = Livewire::test($page, $mount);

        if ($page === TwoFactorSetup::class) {
            // Preparing starts a new setup, which clears the code typed so far.
            $component->call('prepare');
        }

        foreach ($fields as $field => $value) {
            $component->set($field, $value);
        }

        $component->call($action)->assertHasErrors();

        $snapshot = json_encode($component->snapshot);
        $this->assertStringNotContainsString(self::SECRET, $snapshot);
        $this->assertStringNotContainsString(self::CODE, $snapshot);
    }
}
