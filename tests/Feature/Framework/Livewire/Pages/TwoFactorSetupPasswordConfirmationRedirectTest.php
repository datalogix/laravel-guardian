<?php

namespace Datalogix\Guardian\Tests\Feature\Framework\Livewire\Pages;

use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Framework\Livewire\Pages\TwoFactorSetup;
use Datalogix\Guardian\Http\Middleware\Authenticate;
use Datalogix\Guardian\Tests\Attributes\WithFortresses;
use Datalogix\Guardian\Tests\TestCase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Group;

#[Group('livewire')]
class TwoFactorSetupPasswordConfirmationRedirectTest extends TestCase
{
    protected function fortresses(): array
    {
        return [Fortress::make()->basic()->twoFactor()];
    }

    protected function withoutPasswordConfirmation(): array
    {
        // Without passwordConfirmation(), unlike basic().
        return [
            Fortress::make()->id('default')->default()
                ->login()
                ->logout()
                ->twoFactor()
                ->middleware(['web'])
                ->authMiddleware([Authenticate::class]),
        ];
    }

    public function test_prepare_redirects_to_password_confirmation_when_not_recently_confirmed(): void
    {
        $this->actingAs($this->createUser());

        Livewire::test(TwoFactorSetup::class)
            ->call('prepare')
            ->assertRedirect();
    }

    public function test_enable_redirects_to_password_confirmation_when_not_recently_confirmed(): void
    {
        $this->actingAs($this->createUser());

        Livewire::test(TwoFactorSetup::class)
            ->set('code', '123456')
            ->call('enable')
            ->assertRedirect();
    }

    public function test_disable_redirects_to_password_confirmation_when_not_recently_confirmed(): void
    {
        $this->actingAs($this->createUser());

        Livewire::test(TwoFactorSetup::class)
            ->call('disable')
            ->assertRedirect();
    }

    public function test_regenerate_recovery_codes_redirects_to_password_confirmation_when_not_recently_confirmed(): void
    {
        $this->actingAs($this->createUser());

        Livewire::test(TwoFactorSetup::class)
            ->call('regenerateRecoveryCodes')
            ->assertRedirect();
    }

    #[WithFortresses('withoutPasswordConfirmation')]
    public function test_prepare_rethrows_when_there_is_no_password_confirmation_page_to_redirect_to(): void
    {
        $this->actingAs($this->createUser());

        // Livewire turns the rethrown ValidationException into a component error.
        Livewire::test(TwoFactorSetup::class)
            ->call('prepare')
            ->assertHasErrors('password');
    }
}
