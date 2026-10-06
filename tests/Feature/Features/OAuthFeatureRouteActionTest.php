<?php

namespace Datalogix\Guardian\Tests\Feature\Features;

use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Http\Controllers\OAuthController;
use Datalogix\Guardian\Tests\TestCase;
use InvalidArgumentException;

class OAuthFeatureRouteActionTest extends TestCase
{
    public function test_register_routes_rejects_a_closure_route_action(): void
    {
        $fortress = Fortress::make()->basic()->oauth(providers: ['github'], routeAction: fn () => null);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot handle both the redirect and callback routes');

        $fortress->getOAuthFeature()->registerRoutes();
    }

    public function test_register_routes_rejects_an_array_route_action(): void
    {
        $fortress = Fortress::make()->basic()->oauth(providers: ['github'], routeAction: [OAuthController::class, 'redirect']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be a single controller class name');

        $fortress->getOAuthFeature()->registerRoutes();
    }
}
