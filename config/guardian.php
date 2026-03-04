<?php

use Datalogix\Guardian\Enums\Framework;

return [
    'framework' => Framework::tryFrom(env('GUARDIAN_FRAMEWORK')) ?? Framework::Livewire,

    /*
    |--------------------------------------------------------------------------
    | Cache Path
    |--------------------------------------------------------------------------
    |
    | This is the directory that Guardian will use to store cache files that
    | are used to optimize the registration of components.
    |
    | After changing the path, you should run `php artisan guardian:cache-components`.
    |
    */
    'cache_path' => base_path('bootstrap/cache/guardian'),
];
