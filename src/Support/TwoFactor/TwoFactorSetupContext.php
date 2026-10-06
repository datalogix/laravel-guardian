<?php

namespace Datalogix\Guardian\Support\TwoFactor;

use Datalogix\Guardian\Enums\TwoFactorMethod;
use Datalogix\Guardian\Exceptions\PasswordConfirmationException;
use Datalogix\Guardian\Exceptions\TwoFactorSecretDecryptionException;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Http\Concerns\ChecksTwoFactorSetupAccess;
use Datalogix\Guardian\Response\Redirector;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Session;

class TwoFactorSetupContext
{
    use ChecksTwoFactorSetupAccess;

    public function __construct(
        protected TwoFactorUser $twoFactorUser,
        protected Totp $totp,
        protected QrCode $qrCode,
    ) {}

    public function user(): ?object
    {
        $user = Guardian::user();

        if (is_object($user)) {
            return $user;
        }

        $pendingUser = Guardian::getPendingTwoFactorSetupUser();

        return is_object($pendingUser) ? $pendingUser : null;
    }

    public function abortIfCannotAccess(mixed $user): void
    {
        $this->abortIfCannotAccessTwoFactorSetup($user);
    }

    public function authorizedUser(bool $requireAuthenticated = false): ?object
    {
        $user = $requireAuthenticated ? Guardian::user() : $this->user();

        if (! $user || ($requireAuthenticated && ! $user instanceof Model)) {
            return null;
        }

        $this->abortIfCannotAccessTwoFactorSetup($user);

        return $user;
    }

    public function method(): TwoFactorMethod
    {
        return Guardian::hasPendingTwoFactorSetup()
            ? Guardian::getPendingTwoFactorSetupMethod()
            : Guardian::getTwoFactorSetupMethod();
    }

    /**
     * @return array{
     *     enabled: bool,
     *     secretUnreadable: bool,
     *     canDisable: bool,
     *     canManageRecoveryCodes: bool,
     *     recoveryCodesCount: int,
     *     trustedDevices: array<int, array<string, mixed>>,
     * }
     */
    public function summary(?object $user): array
    {
        $fortress = Guardian::getCurrentOrDefaultFortress();

        $summary = [
            'enabled' => false,
            'secretUnreadable' => false,
            'canDisable' => false,
            'canManageRecoveryCodes' => false,
            'recoveryCodesCount' => 0,
            'trustedDevices' => [],
        ];

        try {
            $enabled = $this->twoFactorUser->hasTwoFactorEnabled($user, $fortress);
        } catch (TwoFactorSecretDecryptionException) {
            return [...$summary, 'enabled' => true, 'secretUnreadable' => true, 'canDisable' => true];
        }

        if (! $enabled) {
            return $summary;
        }

        $summary['enabled'] = true;
        $summary['canDisable'] = $this->canDisable($user);
        $summary['canManageRecoveryCodes'] = $this->twoFactorUser->canStoreTwoFactorRecoveryCodes($user);

        if ($summary['canManageRecoveryCodes']) {
            $summary['recoveryCodesCount'] = $this->twoFactorUser->getTwoFactorRecoveryCodesCount($user, $fortress);
        }

        $summary['trustedDevices'] = $this->trustedDevices();

        return $summary;
    }

    public function canDisable(object $user): bool
    {
        if (! $user instanceof Authenticatable) {
            return true;
        }

        try {
            $this->twoFactorUser->hasTwoFactorEnabled($user, Guardian::getCurrentOrDefaultFortress());
        } catch (TwoFactorSecretDecryptionException) {
            return true;
        }

        return ! Guardian::isTwoFactorRequiredFor($user);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function trustedDevices(): array
    {
        $user = Guardian::user();

        return $user instanceof Model ? Guardian::listTrustedTwoFactorDevices($user) : [];
    }

    /**
     * @return array{method: TwoFactorMethod, secret: string, uri: ?string, qrSvg: ?string}|null
     */
    public function pendingSetup(?object $user): ?array
    {
        $session = Guardian::getTwoFactorSetupSession();
        $secret = $session['secret'] ?? null;

        if (! is_object($user) || ! is_string($secret) || blank($secret)) {
            return null;
        }

        $method = TwoFactorMethod::tryFrom((string) ($session['method'] ?? '')) ?? Guardian::getTwoFactorMethod();

        if ($method !== TwoFactorMethod::Totp) {
            return ['method' => $method, 'secret' => $secret, 'uri' => null, 'qrSvg' => null];
        }

        $uri = $this->totp->makeOtpAuthUri($secret, $this->totp->resolveAccountLabel($user));

        return ['method' => $method, 'secret' => $secret, 'uri' => $uri, 'qrSvg' => $this->qrCode->svg($uri)];
    }

    public function redirectToPasswordConfirmation(PasswordConfirmationException $exception)
    {
        $url = Guardian::passwordConfirmationUrl();

        if (! $url) {
            throw $exception;
        }

        Session::put('url.intended', Guardian::getTwoFactorSetupFeature()->getUrl());

        return Redirector::redirect($url);
    }
}
