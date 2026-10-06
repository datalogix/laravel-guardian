<?php

namespace Datalogix\Guardian\Framework\Livewire\Pages;

use Datalogix\Guardian\Actions\DisableTwoFactor;
use Datalogix\Guardian\Actions\EnableTwoFactor;
use Datalogix\Guardian\Actions\PrepareTwoFactorSetup;
use Datalogix\Guardian\Actions\RegenerateTwoFactorRecoveryCodes;
use Datalogix\Guardian\Enums\TwoFactorMethod;
use Datalogix\Guardian\Exceptions\PasswordConfirmationException;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Http\Responses\Concerns\RedirectsToTwoFactorSetup;
use Datalogix\Guardian\Response\Redirector;
use Datalogix\Guardian\Support\TwoFactor\TwoFactorSetupContext;
use Datalogix\Guardian\Support\TwoFactor\TwoFactorUser;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Locked;

class TwoFactorSetup extends Page
{
    use RedirectsToTwoFactorSetup;

    #[Locked]
    public ?TwoFactorMethod $method = null;

    #[Locked]
    public bool $enabled = false;

    #[Locked]
    public bool $secretUnreadable = false;

    public string $code = '';

    #[Locked]
    public ?string $secret = null;

    #[Locked]
    public ?string $uri = null;

    #[Locked]
    public ?string $qrSvg = null;

    #[Locked]
    public bool $canManageRecoveryCodes = false;

    #[Locked]
    public int $recoveryCodesCount = 0;

    #[Locked]
    public array $recoveryCodes = [];

    #[Locked]
    public array $trustedDevices = [];

    #[Locked]
    public bool $awaitingContinueAfterSetup = false;

    protected bool $recoveryCodesFreshlyGenerated = false;

    public function mount(): void
    {
        if (! Guardian::isAuthenticated() && ! Guardian::hasPendingTwoFactorSetup()) {
            Redirector::redirectToLogin(intended: true);

            return;
        }

        $this->setup()->abortIfCannotAccess($this->setup()->user());

        $this->recoveryCodesFreshlyGenerated = false;

        $this->syncState();

        $this->method = $this->setup()->method();

        if (! $this->enabled) {
            $this->buildPendingSetupData();
        }
    }

    public function prepare()
    {
        $user = $this->setup()->authorizedUser();

        if (! $user || ! app(TwoFactorUser::class)->canStoreTwoFactorSecret($user)) {
            return;
        }

        $this->recoveryCodesFreshlyGenerated = false;

        try {
            $setup = app(PrepareTwoFactorSetup::class)($user, $this->method);
        } catch (PasswordConfirmationException $exception) {
            return $this->setup()->redirectToPasswordConfirmation($exception);
        }

        $this->method = TwoFactorMethod::from($setup['method']);
        $this->secret = $setup['secret'];
        $this->uri = $setup['uri'];
        $this->qrSvg = $setup['qr_svg'];
        $this->code = '';
        $this->enabled = false;
    }

    public function enable()
    {
        return $this->forgettingSecrets(function () {
            $user = $this->setup()->authorizedUser();

            if (! $user) {
                return;
            }

            $wasPendingSetup = Guardian::hasPendingTwoFactorSetup();

            $data = $this->validate(EnableTwoFactor::rules());

            try {
                $this->recoveryCodes = app(EnableTwoFactor::class)($user, $data);
            } catch (PasswordConfirmationException $exception) {
                return $this->setup()->redirectToPasswordConfirmation($exception);
            }

            $this->recoveryCodesFreshlyGenerated = true;
            $this->recoveryCodesCount = count($this->recoveryCodes);

            $this->resetPendingSetupFields();
            $this->method = Guardian::getTwoFactorMethod();

            $this->syncState();

            $this->awaitingContinueAfterSetup = $wasPendingSetup;
        }, 'code');
    }

    public function continueAfterSetup()
    {
        if (! $this->awaitingContinueAfterSetup) {
            return;
        }

        return $this->redirectForRequiredEmailVerification() ?? app(Guardian::getLoginFeature()->getResponse());
    }

    public function disable()
    {
        $user = $this->setup()->authorizedUser();

        if (! $user) {
            return;
        }

        try {
            app(DisableTwoFactor::class)($user);
        } catch (PasswordConfirmationException $exception) {
            return $this->setup()->redirectToPasswordConfirmation($exception);
        }

        $this->resetPendingSetupFields();
        $this->awaitingContinueAfterSetup = false;

        $this->syncState();
    }

    public function regenerateRecoveryCodes()
    {
        $user = $this->setup()->authorizedUser();

        if (! $user) {
            return;
        }

        try {
            $this->recoveryCodes = app(RegenerateTwoFactorRecoveryCodes::class)($user);
        } catch (PasswordConfirmationException $exception) {
            return $this->setup()->redirectToPasswordConfirmation($exception);
        }

        $this->recoveryCodesFreshlyGenerated = true;
        $this->recoveryCodesCount = count($this->recoveryCodes);
    }

    public function revokeTrustedDevice(int $deviceId): void
    {
        $user = $this->setup()->authorizedUser(requireAuthenticated: true);

        if (! $user instanceof Model) {
            return;
        }

        Guardian::revokeTrustedTwoFactorDevice($user, $deviceId);

        $this->syncTrustedDevices();
    }

    public function revokeAllTrustedDevices(): void
    {
        $user = $this->setup()->authorizedUser(requireAuthenticated: true);

        if (! $user instanceof Model) {
            return;
        }

        Guardian::revokeAllTrustedTwoFactorDevices($user);
        Guardian::forgetRememberedTwoFactorDevice();

        $this->syncTrustedDevices();
    }

    protected function setup(): TwoFactorSetupContext
    {
        return app(TwoFactorSetupContext::class);
    }

    protected function syncState(): void
    {
        $summary = $this->setup()->summary($this->setup()->user());

        $this->enabled = $summary['enabled'];
        $this->secretUnreadable = $summary['secretUnreadable'];

        if (! $this->enabled || $this->secretUnreadable) {
            $this->resetTwoFactorRecoveryState();

            return;
        }

        $this->canManageRecoveryCodes = $summary['canManageRecoveryCodes'];

        if ($this->canManageRecoveryCodes) {
            if (! $this->recoveryCodesFreshlyGenerated) {
                $this->recoveryCodes = $summary['recoveryCodes'];
            }

            $this->recoveryCodesCount = $summary['recoveryCodesCount'];
        }

        $this->trustedDevices = $summary['trustedDevices'];
    }

    protected function resetTwoFactorRecoveryState(): void
    {
        $this->recoveryCodes = [];
        $this->recoveryCodesCount = 0;
        $this->canManageRecoveryCodes = false;
        $this->trustedDevices = [];
        $this->recoveryCodesFreshlyGenerated = false;
    }

    protected function syncTrustedDevices(): void
    {
        $this->trustedDevices = $this->setup()->trustedDevices();
    }

    protected function buildPendingSetupData(): void
    {
        $pending = $this->setup()->pendingSetup($this->setup()->user());

        if ($pending === null) {
            return;
        }

        $this->method = $pending['method'];
        $this->secret = $pending['secret'];
        $this->uri = $pending['uri'];
        $this->qrSvg = $pending['qrSvg'];
    }

    protected function resetPendingSetupFields(): void
    {
        $this->secret = null;
        $this->uri = null;
        $this->qrSvg = null;
        $this->code = '';
    }
}
