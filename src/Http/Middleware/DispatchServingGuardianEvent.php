<?php

namespace Datalogix\Guardian\Http\Middleware;

use Closure;
use Datalogix\Guardian\Events\ServingGuardian;
use Illuminate\Http\Request;

class DispatchServingGuardianEvent
{
    public function handle(Request $request, Closure $next): mixed
    {
        ServingGuardian::dispatch();

        return $next($request);
    }
}
