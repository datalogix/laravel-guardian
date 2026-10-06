<?php

namespace Datalogix\Guardian\Tests\Feature\Framework\Livewire\Pages;

use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Framework\Livewire\Pages\TwoFactorSetup;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Tests\TestCase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Group;

#[Group('livewire')]
class TwoFactorSetupUnauthorizedActionsTest extends TestCase
{
    protected function fortresses(): array
    {
        return [Fortress::make()->basic()->twoFactor(requireSetupOnLogin: true)];
    }

    protected function componentWithADeletedPendingUser()
    {
        $user = $this->createUser();
        Guardian::startPendingTwoFactorSetup($user, remember: true);

        $component = Livewire::test(TwoFactorSetup::class)->assertOk();

        $user->delete();

        return $component;
    }

    public function test_prepare_is_a_no_op_once_the_pending_user_no_longer_exists(): void
    {
        $this->componentWithADeletedPendingUser()->call('prepare')->assertSet('secret', null);

    }

    public function test_enable_is_a_no_op_once_the_pending_user_no_longer_exists(): void
    {
        $this->componentWithADeletedPendingUser()->set('code', '123456')->call('enable')
            ->assertSet('enabled', false)
            ->assertSet('recoveryCodes', []);

    }

    public function test_disable_is_a_no_op_once_the_pending_user_no_longer_exists(): void
    {
        $this->componentWithADeletedPendingUser()->call('disable')
            ->assertSet('enabled', false)
            ->assertSet('awaitingContinueAfterSetup', false);

    }

    public function test_regenerate_recovery_codes_is_a_no_op_once_the_pending_user_no_longer_exists(): void
    {
        $this->componentWithADeletedPendingUser()->call('regenerateRecoveryCodes')->assertSet('recoveryCodes', []);

    }

    public function test_revoke_trusted_device_is_a_no_op_for_an_unauthenticated_pending_setup_user(): void
    {
        $user = $this->createUser();
        Guardian::startPendingTwoFactorSetup($user, remember: true);

        Livewire::test(TwoFactorSetup::class)
            ->assertOk()
            ->call('revokeTrustedDevice', 1)
            ->assertSet('trustedDevices', []);

    }

    public function test_revoke_all_trusted_devices_is_a_no_op_for_an_unauthenticated_pending_setup_user(): void
    {
        $user = $this->createUser();
        Guardian::startPendingTwoFactorSetup($user, remember: true);

        Livewire::test(TwoFactorSetup::class)
            ->assertOk()
            ->call('revokeAllTrustedDevices')
            ->assertSet('trustedDevices', []);

    }
}
