<?php

namespace Datalogix\Guardian\Actions;

use Datalogix\Guardian\Actions\Concerns\HasRateLimiter;
use Datalogix\Guardian\Actions\Concerns\HasRecentPasswordConfirmation;
use Datalogix\Guardian\Enums\TwoFactorMethod;
use Datalogix\Guardian\Exceptions\PasswordConfirmationException;
use Datalogix\Guardian\Exceptions\TwoFactorSetupException;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Support\TwoFactor\QrCode;
use Datalogix\Guardian\Support\TwoFactor\Totp;
use Illuminate\Database\Eloquent\Model;

class PrepareTwoFactorSetup
{
    use HasRateLimiter;
    use HasRecentPasswordConfirmation;

    public function __invoke(object $user, ?TwoFactorMethod $method = null): array
    {
        if (Guardian::isAuthenticated() && ! $this->passwordWasRecentlyConfirmed()) {
            throw PasswordConfirmationException::requiredForEnablingTwoFactor();
        }

        return $this->throttleAction(
            function () use ($user, $method) {
                $method ??= Guardian::getTwoFactorMethod();
                $totp = app(Totp::class);

                $secret = $totp->generateSecret();

                Guardian::startTwoFactorSetup($secret, $method);

                $account = $totp->resolveAccountLabel($user);

                if (blank($account)) {
                    throw TwoFactorSetupException::invalidAccountLabel();
                }

                $uri = $totp->makeOtpAuthUri($secret, $account);

                if ($user instanceof Model && $method->requiresDelivery()) {
                    Guardian::dispatchTwoFactorCode($user, $method, $totp->currentCode($secret), 'setup');
                }

                return [
                    'secret' => $secret,
                    'uri' => $uri,
                    'qr_svg' => app(QrCode::class)->svg($uri),
                    'method' => $method->value,
                ];
            },
            fn (int $seconds) => throw TwoFactorSetupException::rateLimited($seconds),
            $this->userKey($user),
            Guardian::getTwoFactorSetupFeature()->getMaxAttempts(),
            includeIp: false,
            clearOnSuccess: true,
        );
    }
}
