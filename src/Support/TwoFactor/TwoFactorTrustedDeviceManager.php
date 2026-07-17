<?php

namespace Datalogix\Guardian\Support\TwoFactor;

use Datalogix\Guardian\Events\TwoFactorTrustedDeviceRemembered;
use Datalogix\Guardian\Events\TwoFactorTrustedDeviceRevoked;
use Datalogix\Guardian\Events\TwoFactorTrustedDevicesRevokedAll;
use Datalogix\Guardian\Fortress;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cookie;

class TwoFactorTrustedDeviceManager
{
    public function __construct(
        protected TwoFactorUser $twoFactorUser,
        protected TrustedDevices $trustedDevices,
    ) {}

    public function remember(
        Fortress $fortress,
        Model $user,
        bool $enabled,
        int $days,
        string $cookieName,
    ): void {
        if (! $enabled) {
            return;
        }

        $secret = $this->twoFactorUser->getTwoFactorSecret($user, $fortress);

        if (! is_string($secret) || blank($secret)) {
            return;
        }

        $issued = $this->trustedDevices->issue($fortress, $user, $days);

        if (! is_array($issued)) {
            return;
        }

        Cookie::queue(Cookie::make(
            $cookieName,
            "{$issued['id']}|{$issued['token']}",
            $days * 1440,
            null,
            null,
            request()->isSecure(),
            true,
            false,
            'lax',
        ));

        event(new TwoFactorTrustedDeviceRemembered($fortress, $user, (int) $issued['id']));
    }

    public function forget(string $cookieName): void
    {
        Cookie::queue(Cookie::forget($cookieName));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function list(Fortress $fortress, Model $user): array
    {
        return $this->trustedDevices->list($fortress, $user);
    }

    public function revoke(Fortress $fortress, Model $user, int $deviceId): bool
    {
        $revoked = $this->trustedDevices->revoke($deviceId, $fortress, $user);

        if ($revoked) {
            event(new TwoFactorTrustedDeviceRevoked($fortress, $user, $deviceId));
        }

        return $revoked;
    }

    public function revokeAll(Fortress $fortress, Model $user): int
    {
        $count = $this->trustedDevices->revokeAll($fortress, $user);

        if ($count > 0) {
            event(new TwoFactorTrustedDevicesRevokedAll($fortress, $user, $count));
        }

        return $count;
    }

    public function shouldSkipChallenge(
        Fortress $fortress,
        Model $user,
        bool $enabled,
        string $cookieName,
    ): bool {
        if (! $enabled) {
            return false;
        }

        $cookie = request()->cookie($cookieName);

        if (! is_string($cookie) || blank($cookie)) {
            return false;
        }

        $parts = explode('|', $cookie, 2);

        if (count($parts) !== 2) {
            $this->forget($cookieName);

            return false;
        }

        [$deviceId, $token] = $parts;

        if (! ctype_digit($deviceId) || blank($token)) {
            $this->forget($cookieName);

            return false;
        }

        $trusted = $this->trustedDevices->touchIfValid($fortress, $user, (int) $deviceId, $token);

        if (! $trusted) {
            $this->forget($cookieName);

            return false;
        }

        return true;
    }
}
