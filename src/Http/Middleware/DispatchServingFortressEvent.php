<?php

namespace Datalogix\Fortress\Http\Middleware;

use Closure;
use Datalogix\Fortress\Events\ServingFortress;
use Illuminate\Http\Request;

class DispatchServingFortressEvent
{
    public function handle(Request $request, Closure $next): mixed
    {
        ServingFortress::dispatch();

        return $next($request);
    }
}
