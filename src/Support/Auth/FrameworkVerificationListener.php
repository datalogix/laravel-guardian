<?php

namespace Datalogix\Guardian\Support\Auth;

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

/**
 * Laravel 11+ applications send the e-mail verification notification of every
 * Registered user out of the box (Application::configure()->withEvents()).
 */
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
