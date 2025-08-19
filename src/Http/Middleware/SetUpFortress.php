<?php

namespace Datalogix\Guardian\Http\Middleware;

use Closure;
use Datalogix\Guardian\Guardian;
use Illuminate\Http\Request;

class SetUpFortress
{
    public function handle(Request $request, Closure $next, string $fortress): mixed
    {
        $fortress = Guardian::getFortress($fortress);

        Guardian::setCurrentFortress($fortress);

        Guardian::bootCurrentFortress();

        return $next($request);
    }
}
