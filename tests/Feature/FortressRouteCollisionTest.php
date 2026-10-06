<?php

namespace Datalogix\Guardian\Tests\Feature;

use Datalogix\Guardian\Exceptions\FortressRouteCollisionException;
use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\FortressRegistry;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;

class FortressRouteCollisionTest extends TestCase
{
    protected function loadRoutesOf(Fortress ...$fortresses): void
    {
        $registry = new FortressRegistry;

        foreach ($fortresses as $fortress) {
            $registry->register($fortress);
        }

        $this->app->instance(FortressRegistry::class, $registry);
        Route::setRoutes(new RouteCollection);

        require dirname(__DIR__, 2).'/routes/web.php';

        Route::getRoutes()->refreshNameLookups();
    }

    public function test_two_fortresses_on_the_same_path_fail(): void
    {
        $this->expectException(FortressRouteCollisionException::class);
        $this->expectExceptionMessage('The fortresses [default] and [customer] both register [GET /login].');

        $this->loadRoutesOf(Fortress::make()->basic(), Fortress::make()->basic('customer'));
    }

    public function test_fortresses_on_their_own_paths_pass(): void
    {
        $this->loadRoutesOf(Fortress::make()->basic(), Fortress::make()->admin());

        $this->assertTrue(Route::has('auth.login'));
        $this->assertTrue(Route::has('guardian.admin.auth.login'));
    }

    public function test_fortresses_on_their_own_domains_pass(): void
    {
        $this->loadRoutesOf(
            Fortress::make()->basic()->domain('app.example.com'),
            Fortress::make()->basic('customer')->domain('customer.example.com'),
        );

        $this->assertTrue(Route::has('guardian.customer.auth.login'));
    }

    public function test_a_route_of_the_application_given_to_a_fortress_counts_too(): void
    {
        $this->expectException(FortressRouteCollisionException::class);
        $this->expectExceptionMessage('[GET /reports]');

        $this->loadRoutesOf(
            Fortress::make()->basic()->routes(fn () => Route::get('/reports', fn () => 'default')),
            Fortress::make()->basic('customer')->login(false)->logout(false)->passwordReset(false)->passwordConfirmation(false)
                ->routes(fn () => Route::get('/reports', fn () => 'customer')),
        );
    }
}
