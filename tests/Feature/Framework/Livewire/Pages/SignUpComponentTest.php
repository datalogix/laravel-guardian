<?php

namespace Datalogix\Guardian\Tests\Feature\Framework\Livewire\Pages;

use Datalogix\Guardian\Actions\SignUp as SignUpAction;
use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Framework\Livewire\Pages\SignUp;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Support\Auth\PostAuthenticationFlow;
use Datalogix\Guardian\Tests\Attributes\WithFortresses;
use Datalogix\Guardian\Tests\TestCase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Group;

#[Group('livewire')]
class SignUpComponentTest extends TestCase
{
    protected function fortresses(): array
    {
        return [Fortress::make()->product()->default()];
    }

    public function test_it_renders(): void
    {
        Livewire::test(SignUp::class)->assertOk();
    }

    public function test_submit_creates_a_user(): void
    {
        Livewire::test(SignUp::class)
            ->set('name', 'New User')
            ->set('login', 'new@example.com')
            ->set('password', 'Password123!')
            ->set('password_confirmation', 'Password123!')
            ->set('terms', true)
            ->call('submit');

        $this->assertDatabaseHas('users', ['email' => 'new@example.com']);
        $this->assertTrue(Guardian::isAuthenticated());
    }

    protected function withTerms(): array
    {
        return [Fortress::make()->product()->default()->signUp(termsUrl: 'https://example.com/terms')];
    }

    #[WithFortresses('withTerms')]
    public function test_submit_fails_validation_without_accepting_terms(): void
    {
        Livewire::test(SignUp::class)
            ->set('name', 'New User')
            ->set('login', 'new@example.com')
            ->set('password', 'Password123!')
            ->set('password_confirmation', 'Password123!')
            ->set('terms', false)
            ->call('submit')
            ->assertHasErrors('terms');
    }

    public function test_submit_validates_with_the_rules_of_the_action_the_application_bound(): void
    {
        $this->app->bind(SignUpAction::class, fn ($app) => new class($app->make(PostAuthenticationFlow::class)) extends SignUpAction
        {
            public static function rules(): array
            {
                return [...parent::rules(), 'name' => ['required', 'string', 'max:3']];
            }
        });

        Livewire::test(SignUp::class)
            ->set('name', 'New User')
            ->set('login', 'new@example.com')
            ->set('password', 'Password123!')
            ->set('password_confirmation', 'Password123!')
            ->set('terms', true)
            ->call('submit')
            ->assertHasErrors('name');

        $this->assertDatabaseMissing('users', ['email' => 'new@example.com']);
    }
}
