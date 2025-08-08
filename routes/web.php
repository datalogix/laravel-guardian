<?php

use Datalogix\Fortress\Facades\Fortress as FortressFacade;
use Datalogix\Fortress\Fortress;
use Datalogix\Fortress\Http\Middleware\RedirectIfAuthenticated;
use Illuminate\Support\Facades\Route;

Route::group([], function () {
    foreach (FortressFacade::getFortresses() as $fortress) {
        /** @var Fortress $fortress */
        $domains = $fortress->getDomains();

        foreach ((empty($domains) ? [null] : $domains) as $domain) {
            Route::domain($domain)
                ->middleware($fortress->getMiddleware())
                ->name($fortress->generateRouteName(domain: $domain))
                ->prefix($fortress->getPath())
                ->group(function () use ($fortress) {
                    foreach ($fortress->getRoutes() as $routes) {
                        $routes($fortress);
                    }

                    Route::name('auth.')
                        ->middleware(RedirectIfAuthenticated::class)
                        ->group(function () use ($fortress) {
                            if ($fortress->hasLogin()) {
                                Route::get($fortress->getLoginRouteSlug(), $fortress->getLoginRouteAction())
                                    ->name('login');
                            }

                            if ($fortress->hasPasswordReset()) {
                                Route::name('password.')
                                    ->prefix($fortress->getPasswordResetRoutePrefix())
                                    ->group(function () use ($fortress) {
                                        Route::get($fortress->getPasswordResetRequestRouteSlug(), $fortress->getPasswordResetRequestRouteAction())
                                            ->name('request');
                                        Route::get($fortress->getPasswordResetRouteSlug(), $fortress->getPasswordResetRouteAction())
                                            ->middleware(['signed'])
                                            ->name('reset');
                                    });
                            }

                            if ($fortress->hasRegistration()) {
                                Route::get($fortress->getRegistrationRouteSlug(), $fortress->getRegistrationRouteAction())
                                    ->name('register');
                            }
                        });

                    Route::middleware($fortress->getAuthMiddleware())
                        ->group(function () use ($fortress) {
                            foreach ($fortress->getAuthenticatedRoutes() as $routes) {
                                $routes($fortress);
                            }

                            Route::name('auth.')
                                ->group(function () use ($fortress) {
                                    if ($fortress->hasLogout()) {
                                        Route::any($fortress->getLogoutRouteSlug(), $fortress->getLogoutRouteAction())
                                            ->name('logout');
                                    }

                                    if ($fortress->hasEmailVerification()) {
                                        Route::name('email-verification.')
                                            ->prefix($fortress->getEmailVerificationRoutePrefix())
                                            ->group(function () use ($fortress) {
                                                Route::get($fortress->getEmailVerificationPromptRouteSlug(), $fortress->getEmailVerificationPromptRouteAction())
                                                    ->name('prompt');
                                                Route::get($fortress->getEmailVerificationRouteSlug('/{id}/{hash}'), $fortress->getEmailVerificationRouteAction())
                                                    ->middleware(['signed', 'throttle:6,1'])
                                                    ->name('verify');
                                            });
                                    }
                                });
                        });
                });
        }
    }
});
