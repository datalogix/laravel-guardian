<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cache Path
    |--------------------------------------------------------------------------
    |
    | This is the directory that Fortress will use to store cache files that
    | are used to optimize the registration of components.
    |
    | After changing the path, you should run `php artisan fortress:cache-components`.
    |
    */
    'cache_path' => base_path('bootstrap/cache/fortress'),
];
