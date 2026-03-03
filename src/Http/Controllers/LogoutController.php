<?php

namespace Datalogix\Guardian\Http\Controllers;

use Datalogix\Guardian\Actions\Logout as LogoutAction;
use Datalogix\Guardian\Guardian;

class LogoutController
{
    public function __invoke()
    {
        app(LogoutAction::class)();

        return app(Guardian::getLogoutResponse());
    }
}
