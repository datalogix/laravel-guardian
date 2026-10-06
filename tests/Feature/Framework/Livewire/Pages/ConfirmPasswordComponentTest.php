<?php

namespace Datalogix\Guardian\Tests\Feature\Framework\Livewire\Pages;

use Datalogix\Guardian\Framework\Livewire\Pages\ConfirmPassword;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Group;

#[Group('livewire')]
class ConfirmPasswordComponentTest extends TestCase
{
    public function test_it_renders_for_authenticated_users(): void
    {
        $this->actingAs($this->createUser());

        Livewire::test(ConfirmPassword::class)->assertOk();
    }

    public function test_submit_confirms_a_correct_password(): void
    {
        $user = $this->createUser(['password' => Hash::make('secret123')]);
        $this->actingAs($user);

        Livewire::test(ConfirmPassword::class)
            ->set('password', 'secret123')
            ->call('submit');

        $this->assertNotNull(session('auth.password_confirmed_at'));
    }

    public function test_submit_fails_for_an_incorrect_password(): void
    {
        $user = $this->createUser(['password' => Hash::make('secret123')]);
        $this->actingAs($user);

        Livewire::test(ConfirmPassword::class)
            ->set('password', 'wrong')
            ->call('submit')
            ->assertHasErrors('password');
    }
}
