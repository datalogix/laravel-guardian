<?php

namespace Datalogix\Guardian\Tests\Feature\Framework\Livewire\Pages;

use Datalogix\Guardian\Framework\Livewire\Pages\Login;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Group;

#[Group('livewire')]
class LoginComponentTest extends TestCase
{
    public function test_it_renders(): void
    {
        Livewire::test(Login::class)->assertOk();
    }

    public function test_submit_authenticates_with_valid_credentials(): void
    {
        $user = $this->createUser(['password' => Hash::make('secret123')]);

        Livewire::test(Login::class)
            ->set('login', $user->email)
            ->set('password', 'secret123')
            ->call('submit');

        $this->assertTrue(Guardian::isAuthenticated());
    }

    public function test_submit_fails_validation_with_missing_fields(): void
    {
        Livewire::test(Login::class)
            ->set('login', '')
            ->set('password', '')
            ->call('submit')
            ->assertHasErrors(['login', 'password']);

        $this->assertFalse(Guardian::isAuthenticated());
    }

    public function test_submit_throws_a_validation_exception_for_invalid_credentials(): void
    {
        $user = $this->createUser(['password' => Hash::make('secret123')]);

        Livewire::test(Login::class)
            ->set('login', $user->email)
            ->set('password', 'wrong-password')
            ->call('submit')
            ->assertHasErrors('login');
    }
}
