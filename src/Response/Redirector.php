<?php

namespace Datalogix\Guardian\Response;

use Datalogix\Guardian\Guardian;

class Redirector
{
    public static function redirect(
        ?string $path = null,
        bool $intended = false,
        bool $navigate = true
    ) {
        return Guardian::getCurrentOrDefaultFortress()
            ->getFrameworkAdapter()
            ->redirect($path ?? Guardian::getUrl(), $intended, $navigate);
    }

    public static function redirectIntended(?string $path = null, bool $navigate = true)
    {
        return self::redirect(path: $path, intended: true, navigate: $navigate);
    }

    public static function redirectToLogin(bool $intended = false, bool $navigate = true)
    {
        return self::redirect(Guardian::getLoginFeature()->getUrl(), intended: $intended, navigate: $navigate);
    }
}
