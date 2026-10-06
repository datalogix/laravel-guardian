<?php

namespace Datalogix\Guardian\Tests\Feature\Framework\Inertia;

use Datalogix\Guardian\Enums\IdentifierKey;
use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Guardian;
use PHPUnit\Framework\Attributes\Group;

#[Group('inertia')]
#[Group('socialite')]
class OAuthCompleteRegistrationControllerTest extends InertiaTestCase
{
    protected function fortresses(): array
    {
        return [Fortress::make()->inertia()->basic()->identifierKey(IdentifierKey::CPF)->emailVerification(isRequired: false)->oauth(providers: ['github'])];
    }

    protected function startPendingRegistration(): void
    {
        Guardian::startPendingOAuthRegistration(
            provider: 'github',
            providerUserId: 'gh-1',
            email: 'pending@example.com',
            name: 'Pending User',
            avatar: null,
        );
    }

    public function test_it_renders_with_a_pending_registration(): void
    {
        $this->startPendingRegistration();

        $this->assertPage($this->inertiaGet('/oauth/complete-registration'), 'Guardian/OAuthCompleteRegistration', [
            'identifierKey' => 'cpf',
            'provider' => 'github',
            'email' => 'pending@example.com',
            'endpoints.submit' => url('/oauth/complete-registration'),
        ]);
    }

    public function test_it_redirects_without_a_pending_registration(): void
    {
        $this->inertiaGet('/oauth/complete-registration')->assertRedirect();
    }

    public function test_submit_completes_the_registration(): void
    {
        $this->startPendingRegistration();

        $this->inertiaPost('/oauth/complete-registration', ['login' => '529.982.247-25'])->assertRedirect();

        $this->assertDatabaseHas('users', ['email' => 'pending@example.com', 'cpf' => '52998224725']);
        $this->assertAuthenticated();
    }

    public function test_submit_validates_the_identifier(): void
    {
        $this->startPendingRegistration();

        $this->inertiaPost('/oauth/complete-registration', ['login' => 'not-a-cpf'])->assertSessionHasErrors('login');

        $this->assertGuest();
    }
}
