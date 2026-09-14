<?php

namespace Datalogix\Guardian\Http\Livewire;

use Datalogix\Guardian\Actions\DisableTwoFactor;
use Datalogix\Guardian\Actions\EnableTwoFactor;
use Datalogix\Guardian\Actions\PrepareTwoFactorSetup;
use Datalogix\Guardian\Actions\RegenerateTwoFactorRecoveryCodes;
use Datalogix\Guardian\Enums\TwoFactorMethod;
use Datalogix\Guardian\Exceptions\PasswordConfirmationException;
use Datalogix\Guardian\Exceptions\TwoFactorSecretDecryptionException;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Http\Concerns\ChecksTwoFactorSetupAccess;
use Datalogix\Guardian\Http\Responses\Concerns\RedirectsToTwoFactorSetup;
use Datalogix\Guardian\Response\Redirector;
use Datalogix\Guardian\Support\TwoFactor\QrCode;
use Datalogix\Guardian\Support\TwoFactor\Totp;
use Datalogix\Guardian\Support\TwoFactor\TwoFactorUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Session;

class TwoFactorSetup extends Page
{
    use ChecksTwoFactorSetupAccess;
    use RedirectsToTwoFactorSetup;

    public ?TwoFactorMethod $method = null;

    public bool $enabled = false;

    public bool $secretUnreadable = false;

    public string $code = '';

    public ?string $secret = null;

    public ?string $uri = null;

    public ?string $qrSvg = null;

    public bool $canManageRecoveryCodes = false;

    public int $recoveryCodesCount = 0;

    public array $recoveryCodes = [];

    public array $trustedDevices = [];

    public bool $awaitingContinueAfterSetup = false;

    protected bool $recoveryCodesFreshlyGenerated = false;

    public function mount(): void
    {
        if (! Guardian::isAuthenticated() && ! Guardian::hasPendingTwoFactorSetup()) {
            Redirector::redirectToLogin(intended: true);

            return;
        }

        $this->abortIfCannotAccessTwoFactorSetup($this->resolveSetupUser());

        $this->recoveryCodesFreshlyGenerated = false;

        $this->syncState();

        $this->method = Guardian::hasPendingTwoFactorSetup()
            ? Guardian::getPendingTwoFactorSetupMethod()
            : Guardian::getTwoFactorSetupMethod();

        if (! $this->enabled) {
            $this->buildPendingSetupData();
        }
    }

    public function prepare()
    {
        $user = $this->resolveAuthorizedUser();

        if (! $user || ! app(TwoFactorUser::class)->canStoreTwoFactorSecret($user)) {
            return;
        }

        $this->recoveryCodesFreshlyGenerated = false;

        try {
            $setup = app(PrepareTwoFactorSetup::class)($user, $this->method);
        } catch (PasswordConfirmationException $exception) {
            return $this->redirectToPasswordConfirmation($exception);
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
        $user = $this->resolveAuthorizedUser();

        if (! $user) {
            return;
        }

        $wasPendingSetup = Guardian::hasPendingTwoFactorSetup();

        $data = $this->validate(EnableTwoFactor::rules());

        try {
            $this->recoveryCodes = app(EnableTwoFactor::class)($user, $data);
        } catch (PasswordConfirmationException $exception) {
            return $this->redirectToPasswordConfirmation($exception);
        }

        $this->recoveryCodesFreshlyGenerated = true;
        $this->recoveryCodesCount = count($this->recoveryCodes);

        $this->resetPendingSetupFields();
        $this->method = Guardian::getTwoFactorMethod();

        $this->syncState();

        $this->awaitingContinueAfterSetup = $wasPendingSetup;
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
        $user = $this->resolveAuthorizedUser();

        if (! $user) {
            return;
        }

        try {
            app(DisableTwoFactor::class)($user);
        } catch (PasswordConfirmationException $exception) {
            return $this->redirectToPasswordConfirmation($exception);
        }

        $this->resetPendingSetupFields();
        $this->awaitingContinueAfterSetup = false;

        $this->syncState();
    }

    public function regenerateRecoveryCodes()
    {
        $user = $this->resolveAuthorizedUser();

        if (! $user) {
            return;
        }

        try {
            $this->recoveryCodes = app(RegenerateTwoFactorRecoveryCodes::class)($user);
        } catch (PasswordConfirmationException $exception) {
            return $this->redirectToPasswordConfirmation($exception);
        }

        $this->recoveryCodesFreshlyGenerated = true;
        $this->recoveryCodesCount = count($this->recoveryCodes);
    }

    public function revokeTrustedDevice(int $deviceId): void
    {
        $user = $this->resolveAuthorizedUser(requireAuthenticated: true);

        if (! $user instanceof Model) {
            return;
        }

        Guardian::revokeTrustedTwoFactorDevice($user, $deviceId);

        $this->syncTrustedDevices();
    }

    public function revokeAllTrustedDevices(): void
    {
        $user = $this->resolveAuthorizedUser(requireAuthenticated: true);

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
            $this->resetTwoFactorRecoveryState();

            return;
        }

        $fortress = Guardian::getCurrentOrDefaultFortress();

        $this->secretUnreadable = false;

        try {
            $this->enabled = $manager->hasTwoFactorEnabled($user, $fortress);
        } catch (TwoFactorSecretDecryptionException) {
            $this->enabled = true;
            $this->secretUnreadable = true;
            $this->resetTwoFactorRecoveryState();

            return;
        }

        if (! $this->enabled) {
            $this->resetTwoFactorRecoveryState();

            return;
        }

        $this->canManageRecoveryCodes = $manager->canStoreTwoFactorRecoveryCodes($user);

        if ($this->canManageRecoveryCodes) {
            if (! $this->recoveryCodesFreshlyGenerated) {
                $this->recoveryCodes = array_values(array_filter(
                    $manager->getTwoFactorRecoveryCodes($user, $fortress),
                    fn ($code) => is_string($code) && filled($code),
                ));
            }

            $this->recoveryCodesCount = $manager->getTwoFactorRecoveryCodesCount($user, $fortress);
        }

        $this->syncTrustedDevices();
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
        $session = Guardian::getTwoFactorSetupSession();
        $secret = $session['secret'] ?? null;

        if (! is_object($user) || ! is_string($secret) || blank($secret)) {
            return;
        }

        $method = TwoFactorMethod::tryFrom((string) ($session['method'] ?? '')) ?? Guardian::getTwoFactorMethod();

        $this->method = $method;

        if ($method !== TwoFactorMethod::Totp) {
            $this->uri = null;
            $this->qrSvg = null;
            $this->secret = $secret;

            return;
        }

        $account = app(Totp::class)->resolveAccountLabel($user);

        $this->secret = $secret;
        $this->uri = app(Totp::class)->makeOtpAuthUri($secret, $account);
        $this->qrSvg = app(QrCode::class)->svg($this->uri);
    }

    protected function redirectToPasswordConfirmation(PasswordConfirmationException $exception)
    {
        $url = Guardian::passwordConfirmationUrl();

        if (! $url) {
            throw $exception;
        }

        Session::put('url.intended', Guardian::getTwoFactorSetupFeature()->getUrl());

        return Redirector::redirect($url);
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

    protected function resolveAuthorizedUser(bool $requireAuthenticated = false): ?object
    {
        $user = $requireAuthenticated ? Guardian::user() : $this->resolveSetupUser();

        if ($requireAuthenticated && ! $user instanceof Model) {
            return null;
        }

        if (! $user) {
            return null;
        }

        $this->abortIfCannotAccessTwoFactorSetup($user);

        return $user;
    }

    protected function resetPendingSetupFields(): void
    {
        $this->secret = null;
        $this->uri = null;
        $this->qrSvg = null;
        $this->code = '';
    }
}
