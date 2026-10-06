<?php

namespace Datalogix\Guardian\Response;

use Datalogix\Guardian\Guardian;

class Notifier
{
    public static function notify(string $message, ?string $type = null): void
    {
        Guardian::getCurrentOrDefaultFortress()
            ->getFrameworkAdapter()
            ->notify($message, $type);
    }
}
