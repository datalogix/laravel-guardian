<?php

namespace Datalogix\Guardian\Actions;

use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Support\QrCode;
use Datalogix\Guardian\Support\Totp;
use Exception;

class PrepareTwoFactorSetup
{
    public function __invoke(object $user): array
    {
        $secret = app(Totp::class)->generateSecret();

        Guardian::startTwoFactorSetup($secret);

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

        return [
            'secret' => $secret,
            'uri' => $uri,
            'qr_svg' => app(QrCode::class)->svg($uri),
        ];
    }
}
