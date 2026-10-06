<?php

namespace Datalogix\Guardian\Support\Auth;

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

class FrameworkVerificationListener
{
    public static function isRegistered(): bool
    {
        return collect(Event::getRawListeners()[Registered::class] ?? [])->contains(
            fn (mixed $listener) => match (true) {
                is_string($listener) => Str::before($listener, '@') === SendEmailVerificationNotification::class,
                is_array($listener) => ($listener[0] ?? null) === SendEmailVerificationNotification::class,
                default => false,
            }
        );
    }
}
