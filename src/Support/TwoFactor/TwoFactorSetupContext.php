<?php

namespace Datalogix\Guardian\Support\TwoFactor;

use Datalogix\Guardian\Enums\TwoFactorMethod;
use Datalogix\Guardian\Exceptions\PasswordConfirmationException;
use Datalogix\Guardian\Exceptions\TwoFactorSecretDecryptionException;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Http\Concerns\ChecksTwoFactorSetupAccess;
use Datalogix\Guardian\Response\Redirector;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Session;

/**
 * What a two-factor setup page needs to know, whatever front-end renders it:
 * who is setting up, in which state they are and where to send them when a
 * sensitive action needs the password to be confirmed again.
 */
class TwoFactorSetupContext
{
    use ChecksTwoFactorSetupAccess;

    public function __construct(
        protected TwoFactorUser $twoFactorUser,
        protected Totp $totp,
        protected QrCode $qrCode,
    ) {
        //
    }

    /**
     * The authenticated user or, while signing in, the user with a pending setup.
     */
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

    /**
     * The user allowed to act on the setup, or null when there is none.
     * Aborts with a 403 when the user cannot access the fortress.
     */
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
     *     canManageRecoveryCodes: bool,
     *     recoveryCodesCount: int,
     *     recoveryCodes: array<int, string>,
     *     trustedDevices: array<int, array<string, mixed>>,
     * }
     */
    public function summary(?object $user): array
    {
        $fortress = Guardian::getCurrentOrDefaultFortress();

        $summary = [
            'enabled' => false,
            'secretUnreadable' => false,
            'canManageRecoveryCodes' => false,
            'recoveryCodesCount' => 0,
            'recoveryCodes' => [],
            'trustedDevices' => [],
        ];

        try {
            $enabled = $this->twoFactorUser->hasTwoFactorEnabled($user, $fortress);
        } catch (TwoFactorSecretDecryptionException) {
            return [...$summary, 'enabled' => true, 'secretUnreadable' => true];
        }

        if (! $enabled) {
            return $summary;
        }

        $summary['enabled'] = true;
        $summary['canManageRecoveryCodes'] = $this->twoFactorUser->canStoreTwoFactorRecoveryCodes($user);

        if ($summary['canManageRecoveryCodes']) {
            $summary['recoveryCodes'] = $this->twoFactorUser->getTwoFactorRecoveryCodes($user, $fortress);
            $summary['recoveryCodesCount'] = $this->twoFactorUser->getTwoFactorRecoveryCodesCount($user, $fortress);
        }

        $summary['trustedDevices'] = $this->trustedDevices();

        return $summary;
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
     * The secret waiting to be confirmed, if a setup was prepared.
     *
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

    /**
     * Sends the user to confirm their password and back to the setup afterwards.
     */
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
