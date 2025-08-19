<?php

return [
    'framework' => env('GUARDIAN_FRAMEWORK', 'livewire'),

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
