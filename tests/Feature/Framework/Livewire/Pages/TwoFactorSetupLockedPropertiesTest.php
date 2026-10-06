<?php

namespace Datalogix\Guardian\Tests\Feature\Framework\Livewire\Pages;

use Datalogix\Guardian\Enums\TwoFactorMethod;
use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Framework\Livewire\Pages\TwoFactorSetup;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Tests\TestCase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * The state of the setup page is the server's: everything the page shows is
 * read-only for the browser, apart from the code the user types.
 */
#[Group('livewire')]
class TwoFactorSetupLockedPropertiesTest extends TestCase
{
    protected function fortresses(): array
    {
        return [Fortress::make()->basic()->twoFactor(method: TwoFactorMethod::Totp)];
    }

    public static function lockedProperties(): array
    {
        return [
            'method' => ['method', 'sms'],
            'enabled' => ['enabled', true],
            'secretUnreadable' => ['secretUnreadable', true],
            'secret' => ['secret', 'JBSWY3DPEHPK3PXP'],
            'uri' => ['uri', 'otpauth://totp/x'],
            'qrSvg' => ['qrSvg', '<svg></svg>'],
            'canManageRecoveryCodes' => ['canManageRecoveryCodes', true],
            'recoveryCodesCount' => ['recoveryCodesCount', 99],
            'recoveryCodes' => ['recoveryCodes', ['forged']],
            'trustedDevices' => ['trustedDevices', [['id' => 1]]],
            'awaitingContinueAfterSetup' => ['awaitingContinueAfterSetup', true],
        ];
    }

    protected function signIn(): void
    {
        $this->actingAs($this->createUser());
        session()->put('auth.password_confirmed_at', time());
    }

    #[DataProvider('lockedProperties')]
    public function test_the_browser_cannot_change_the_state_of_the_page(string $property, mixed $value): void
    {
        $this->signIn();

        $this->expectException(CannotUpdateLockedPropertyException::class);
        $this->expectExceptionMessage("[{$property}]");

        Livewire::test(TwoFactorSetup::class)->set($property, $value);
    }

    public function test_a_method_the_fortress_did_not_configure_cannot_be_chosen(): void
    {
        $this->signIn();

        try {
            Livewire::test(TwoFactorSetup::class)->set('method', 'sms')->call('prepare');
            $this->fail('The browser changed a locked property.');
        } catch (CannotUpdateLockedPropertyException) {
            $this->assertNull(Guardian::getTwoFactorSetupSession());
        }
    }

    public function test_the_code_is_still_the_users_to_type(): void
    {
        $this->signIn();

        Livewire::test(TwoFactorSetup::class)->set('code', '123456')->assertSet('code', '123456');
    }

    public function test_the_server_still_updates_the_locked_state(): void
    {
        $this->signIn();

        Livewire::test(TwoFactorSetup::class)
            ->call('prepare')
            ->assertSet('method', TwoFactorMethod::Totp)
            ->assertNotSet('secret', null);
    }
}
