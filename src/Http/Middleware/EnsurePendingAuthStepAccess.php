<?php

namespace Datalogix\Guardian\Http\Middleware;

use Closure;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Support\Auth\PendingAuthStep;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

abstract class EnsurePendingAuthStepAccess
{
    public function __construct(protected PendingAuthStep $step)
    {
        //
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->step->hasPendingState()) {
            return redirect()->guest(Guardian::getLoginFeature()->getUrl());
        }

        if (! $this->step->isValid()) {
            $this->step->clear();

            return redirect()->guest(Guardian::getLoginFeature()->getUrl());
        }

        $this->step->authorize($this->step->resolveUser());

        return $next($request);
    }
}
