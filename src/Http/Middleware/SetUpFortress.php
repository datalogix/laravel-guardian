<?php

namespace Datalogix\Fortress\Http\Middleware;

use Closure;
use Datalogix\Fortress\Facades\Fortress;
use Illuminate\Http\Request;

class SetUpFortress
{
    public function handle(Request $request, Closure $next, string $fortress): mixed
    {
        $fortress = Fortress::getFortress($fortress);

        Fortress::setCurrentFortress($fortress);

        Fortress::bootCurrentFortress();

        return $next($request);
    }
}
