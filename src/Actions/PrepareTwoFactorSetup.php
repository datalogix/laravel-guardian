<?php

namespace Datalogix\Guardian\Actions;

use Datalogix\Guardian\Enums\TwoFactorMethod;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Support\TwoFactor\QrCode;
use Datalogix\Guardian\Support\TwoFactor\Totp;
use Exception;

class PrepareTwoFactorSetup
{
    public function __invoke(object $user, ?TwoFactorMethod $method = null): array
    {
        $method ??= Guardian::getTwoFactorMethod();

        $secret = app(Totp::class)->generateSecret();

        Guardian::startTwoFactorSetup($secret, $method);

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

        if (! is_string($account) || blank($account)) {
            throw new Exception('Could not determine a valid account label for two-factor setup.');
        }

        $uri = app(Totp::class)->makeOtpAuthUri($secret, $account);

        if ($user instanceof \Illuminate\Database\Eloquent\Model && $method !== TwoFactorMethod::Totp) {
            Guardian::dispatchTwoFactorCode($user, $method, app(Totp::class)->currentCode($secret), 'setup');
        }

        return [
            'secret' => $secret,
            'uri' => $uri,
            'qr_svg' => app(QrCode::class)->svg($uri),
            'method' => $method->value,
        ];
    }
}
