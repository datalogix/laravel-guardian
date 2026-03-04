<?php

namespace Datalogix\Guardian\Response;

class Notifier
{
    public static function notify(string $message, ?string $type = null): void
    {
        if (app()->bound('tallkit')) {
            app('tallkit')->alert($message, $type);
        } else {
            session()->flash('status', __($message));
        }
    }
}
