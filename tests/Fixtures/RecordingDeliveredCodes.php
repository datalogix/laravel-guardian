<?php

namespace Datalogix\Guardian\Tests\Fixtures;

use Datalogix\Guardian\Support\TwoFactor\DeliveredCodes;

/**
 * Remembers the last code sent by e-mail or SMS, which the user would read there.
 */
class RecordingDeliveredCodes extends DeliveredCodes
{
    public ?string $lastIssued = null;

    public function issue(string $sessionKey, int|false|null $ttl): ?string
    {
        return $this->lastIssued = parent::issue($sessionKey, $ttl);
    }
}
