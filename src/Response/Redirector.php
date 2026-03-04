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
        $path ??= Guardian::getUrl();
        $livewire = app('livewire')?->current();

        if ($livewire) {
            return $intended
                ? $livewire->redirectIntended($path, navigate: $navigate)
                : $livewire->redirect($path, navigate: $navigate);
        }

        return $intended
            ? redirect()->intended($path)
            : redirect()->to($path);
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
