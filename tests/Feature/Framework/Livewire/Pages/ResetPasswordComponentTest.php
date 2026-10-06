<?php

namespace Datalogix\Guardian\Tests\Feature\Framework\Livewire\Pages;

use Datalogix\Guardian\Framework\Livewire\Pages\ResetPassword;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Group;

#[Group('livewire')]
class ResetPasswordComponentTest extends TestCase
{
    public function test_it_renders_and_prefills_token_and_login_from_the_request(): void
    {
        $user = $this->createUser();
        $token = Password::broker()->createToken($user);

        Livewire::test(ResetPassword::class, ['token' => $token, 'login' => $user->email])
            ->assertOk()
            ->assertSet('token', $token)
            ->assertSet('login', $user->email);
    }

    public function test_submit_resets_the_password_with_a_valid_token(): void
    {
        $user = $this->createUser(['password' => Hash::make('old-password')]);
        $token = Password::broker()->createToken($user);

        Livewire::test(ResetPassword::class, ['token' => $token, 'login' => $user->email])
            ->set('password', 'NewPassword123!')
            ->set('password_confirmation', 'NewPassword123!')
            ->call('submit');

        $this->assertTrue(Hash::check('NewPassword123!', $user->fresh()->password));
    }

    public function test_submit_fails_for_an_invalid_token(): void
    {
        $user = $this->createUser();

        Livewire::test(ResetPassword::class, ['token' => 'invalid', 'login' => $user->email])
            ->set('password', 'NewPassword123!')
            ->set('password_confirmation', 'NewPassword123!')
            ->call('submit')
            ->assertHasErrors('login');
    }
}
