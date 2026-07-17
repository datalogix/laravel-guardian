<?php

namespace Datalogix\Guardian\Http\Livewire;

use Datalogix\Guardian\Actions\DisableTwoFactor;
use Datalogix\Guardian\Actions\EnableTwoFactor;
use Datalogix\Guardian\Actions\PrepareTwoFactorSetup;
use Datalogix\Guardian\Actions\RegenerateTwoFactorRecoveryCodes;
use Datalogix\Guardian\Enums\TwoFactorMethod;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Response\Redirector;
use Datalogix\Guardian\Support\TwoFactor\QrCode;
use Datalogix\Guardian\Support\TwoFactor\Totp;
use Datalogix\Guardian\Support\TwoFactor\TwoFactorUser;
use Illuminate\Database\Eloquent\Model;

class TwoFactorSetup extends Page
{
    public ?TwoFactorMethod $method = null;

    public bool $enabled = false;

    public string $code = '';

    public ?string $secret = null;

    public ?string $uri = null;

    public ?string $qrSvg = null;

    public bool $canManageRecoveryCodes = false;

    public int $recoveryCodesCount = 0;

    /**
     * @var array<int, string>
     */
    public array $recoveryCodes = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $trustedDevices = [];

    public function mount(): void
    {
        if (! Guardian::isAuthenticated() && ! Guardian::hasPendingTwoFactorSetup()) {
            Redirector::redirectToLogin(intended: true);

            return;
        }

        $this->syncState();

        $this->method = Guardian::getTwoFactorSetupMethod();

        if (! $this->enabled && filled(Guardian::getTwoFactorSetupSecret())) {
            $this->buildPendingSetupData();
        }
    }

    public function prepare(): void
    {
        $user = $this->resolveSetupUser();

        if (! is_object($user) || ! app(TwoFactorUser::class)->canStoreTwoFactorSecret($user)) {
            return;
        }

        $setup = app(PrepareTwoFactorSetup::class)($user, $this->method);

        $this->method = TwoFactorMethod::from($setup['method']);
        $this->secret = $setup['secret'];
        $this->uri = $setup['uri'];
        $this->qrSvg = $setup['qr_svg'];
        $this->code = '';
        $this->enabled = false;
    }

    public function enable()
    {
        $user = $this->resolveSetupUser();

        if (! is_object($user)) {
            return;
        }

        $wasPendingSetup = Guardian::hasPendingTwoFactorSetup();

        $data = $this->validate(EnableTwoFactor::rules());

        $this->recoveryCodes = app(EnableTwoFactor::class)($user, $data);
        $this->recoveryCodesCount = count($this->recoveryCodes);

        $this->secret = null;
        $this->uri = null;
        $this->qrSvg = null;
        $this->code = '';
        $this->method = Guardian::getTwoFactorMethod();

        $this->syncState();

        if ($wasPendingSetup) {
            return app(Guardian::getLoginFeature()->getResponse());
        }

        return app(Guardian::getTwoFactorSetupFeature()->getResponse());
    }

    public function disable()
    {
        $user = $this->resolveSetupUser();

        if (! is_object($user)) {
            return;
        }

        app(DisableTwoFactor::class)($user);

        $this->secret = null;
        $this->uri = null;
        $this->qrSvg = null;
        $this->code = '';

        $this->syncState();

        return app(Guardian::getTwoFactorSetupFeature()->getResponse());
    }

    public function regenerateRecoveryCodes(): void
    {
        $user = $this->resolveSetupUser();

        if (! is_object($user)) {
            return;
        }

        $this->recoveryCodes = app(RegenerateTwoFactorRecoveryCodes::class)($user);
        $this->recoveryCodesCount = count($this->recoveryCodes);
    }

    public function revokeTrustedDevice(int $deviceId): void
    {
        $user = Guardian::user();

        if (! $user instanceof Model) {
            return;
        }

        Guardian::revokeTrustedTwoFactorDevice($user, $deviceId);

        $this->syncTrustedDevices();
    }

    public function revokeAllTrustedDevices(): void
    {
        $user = Guardian::user();

        if (! $user instanceof Model) {
            return;
        }

        Guardian::revokeAllTrustedTwoFactorDevices($user);
        Guardian::forgetRememberedTwoFactorDevice();

        $this->syncTrustedDevices();
    }

    protected function syncState(): void
    {
        $user = $this->resolveSetupUser();
        $manager = app(TwoFactorUser::class);

        if (! is_object($user)) {
            $this->enabled = false;
            $this->recoveryCodes = [];
            $this->recoveryCodesCount = 0;
            $this->canManageRecoveryCodes = false;
            $this->trustedDevices = [];

            return;
        }

        $fortress = Guardian::getCurrentOrDefaultFortress();

        $this->enabled = $manager->hasTwoFactorEnabled($user, $fortress);

        if (! $this->enabled) {
            $this->recoveryCodes = [];
            $this->recoveryCodesCount = 0;
            $this->canManageRecoveryCodes = false;
            $this->trustedDevices = [];

            return;
        }

        $this->canManageRecoveryCodes = $manager->canStoreTwoFactorRecoveryCodes($user);

        if ($this->canManageRecoveryCodes) {
            $this->recoveryCodes = array_values(array_filter(
                $manager->getTwoFactorRecoveryCodes($user, $fortress),
                fn ($code) => is_string($code) && filled($code),
            ));
            $this->recoveryCodesCount = $manager->getTwoFactorRecoveryCodesCount($user, $fortress);
        }

        $this->syncTrustedDevices();
    }

    protected function syncTrustedDevices(): void
    {
        $user = Guardian::user();

        if (! $user instanceof Model) {
            $this->trustedDevices = [];

            return;
        }

        $this->trustedDevices = Guardian::listTrustedTwoFactorDevices($user);
    }

    protected function buildPendingSetupData(): void
    {
        $user = $this->resolveSetupUser();
        $secret = Guardian::getTwoFactorSetupSecret();
        $method = Guardian::getTwoFactorSetupMethod();

        if (! is_object($user) || ! is_string($secret) || blank($secret)) {
            return;
        }

        $this->method = $method;

        if ($method !== TwoFactorMethod::Totp) {
            $this->uri = null;
            $this->qrSvg = null;
            $this->secret = $secret;

            return;
        }

        $account = 'user';

        if (method_exists($user, 'getAuthIdentifier')) {
            $identifier = $user->getAuthIdentifier();

            if (is_scalar($identifier) && filled((string) $identifier)) {
                $account = (string) $identifier;
            }
        }

        if (method_exists($user, 'getEmailForVerification')) {
            $email = $user->getEmailForVerification();

            if (is_string($email) && filled($email)) {
                $account = $email;
            }
        }

        $this->secret = $secret;
        $this->uri = app(Totp::class)->makeOtpAuthUri($secret, $account);
        $this->qrSvg = app(QrCode::class)->svg($this->uri);
    }

    protected function resolveSetupUser(): ?object
    {
        $user = Guardian::user();

        if (is_object($user)) {
            return $user;
        }

        $pendingUser = Guardian::getPendingTwoFactorSetupUser();

        return is_object($pendingUser) ? $pendingUser : null;
    }
}
