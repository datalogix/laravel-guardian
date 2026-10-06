<?php

use Datalogix\Guardian\Enums\Framework;

return [
    /*
    |--------------------------------------------------------------------------
    | Framework
    |--------------------------------------------------------------------------
    |
    | The front-end integration used by the bundled pages: Framework::Livewire
    | (requires livewire/livewire) or Framework::Inertia (requires
    | inertiajs/inertia-laravel). A fortress can override it with ->livewire()
    | or ->inertia().
    |
    | The options of a front-end that differ per fortress are given to the
    | fortress itself: ->inertia(prefix: 'Admin') or ->livewire(views: 'admin.auth').
    | The Livewire component cache path can be set here:
    |
    |   'livewire' => ['cache_path' => base_path('bootstrap/cache/guardian')],
    |
    */
    'framework' => Framework::tryFrom(env('GUARDIAN_FRAMEWORK')) ?? Framework::Livewire,

    /*
    |--------------------------------------------------------------------------
    | Two-factor codes
    |--------------------------------------------------------------------------
    |
    | The e-mail and SMS two-factor codes are queued notifications. A code expires
    | shortly, so give them a queue that is not held up by slower jobs, or send
    | them right away with the "sync" connection. Null uses the defaults.
    |
    */
    'two_factor_codes' => [
        'connection' => env('GUARDIAN_TWO_FACTOR_CODES_CONNECTION'),
        'queue' => env('GUARDIAN_TWO_FACTOR_CODES_QUEUE'),
    ],
];
