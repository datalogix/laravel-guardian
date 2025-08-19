<?php

use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Guardian;
use Illuminate\Support\Facades\Route;

Route::group([], function () {
    foreach (Guardian::getFortresses() as $fortress) {
        /** @var Fortress $fortress */
        $domains = $fortress->getDomains();

        foreach ((empty($domains) ? [null] : $domains) as $domain) {
            Route::domain($domain)
                ->middleware($fortress->getMiddleware())
                ->name($fortress->generateRouteName(domain: $domain))
                ->prefix($fortress->getPath())
                ->group(function () use ($fortress) {
                    $fortress
                        ->registerRoutes()
                        ->loginRoutes()
                        ->logoutRoutes()
                        ->passwordResetRoutes()
                        ->signUpRoutes()
                        ->emailVerificationRoutes()
                        ->passwordConfirmationRoutes();
                });
        }
    }
});
