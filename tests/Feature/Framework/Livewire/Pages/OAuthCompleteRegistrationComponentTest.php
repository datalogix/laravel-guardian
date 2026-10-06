<?php

namespace Datalogix\Guardian\Tests\Feature\Framework\Livewire\Pages;

use Datalogix\Guardian\Enums\IdentifierKey;
use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Framework\Livewire\Pages\OAuthCompleteRegistration;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Tests\TestCase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Group;

#[Group('livewire')]
#[Group('socialite')]
class OAuthCompleteRegistrationComponentTest extends TestCase
{
    protected function fortresses(): array
    {
        return [Fortress::make()->basic()->identifierKey(IdentifierKey::CPF)->emailVerification(isRequired: false)->oauth(providers: ['github'])];
    }

    protected function startPendingRegistration(): void
    {
        Guardian::startPendingOAuthRegistration(
            provider: 'github',
            providerUserId: 'gh-1',
            email: 'pending@example.com',
            name: 'Pending User',
            avatar: null,
            emailVerified: true,
        );
    }

    public function test_it_renders_with_a_pending_registration(): void
    {
        $this->startPendingRegistration();

        Livewire::test(OAuthCompleteRegistration::class)->assertOk();
    }

    public function test_submit_completes_the_registration(): void
    {
        $this->startPendingRegistration();

        Livewire::test(OAuthCompleteRegistration::class)
            ->set('login', '529.982.247-25')
            ->call('submit');

        $this->assertDatabaseHas('users', ['email' => 'pending@example.com', 'cpf' => '529.982.247-25']);
        $this->assertTrue(Guardian::isAuthenticated());
    }

    public function test_submit_validates_the_login_field(): void
    {
        $this->startPendingRegistration();

        Livewire::test(OAuthCompleteRegistration::class)
            ->set('login', 'not-a-cpf')
            ->call('submit')
            ->assertHasErrors('login');
    }
}
