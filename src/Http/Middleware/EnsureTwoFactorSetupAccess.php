<?php

namespace Datalogix\Guardian\Http\Middleware;

use Closure;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Support\TwoFactor\PendingTwoFactorSetupStep;
use Illuminate\Http\Request;
use Illuminate\Pipeline\Pipeline;
use Symfony\Component\HttpFoundation\Response;

/**
 * The route also serves a user still signing in, so it cannot carry the auth
 * middleware: a signed in user goes through it here.
 */
class EnsureTwoFactorSetupAccess extends EnsurePendingAuthStepAccess
{
    public function __construct(PendingTwoFactorSetupStep $step)
    {
        parent::__construct($step);
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! Guardian::isAuthenticated()) {
            return parent::handle($request, $next);
        }

        return app(Pipeline::class)
            ->send($request)
            ->through(Guardian::getAuthMiddleware())
            ->then(fn (Request $request) => parent::handle($request, $next));
    }
}
