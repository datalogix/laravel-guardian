<?php

namespace Datalogix\Guardian\Actions;

use Datalogix\Guardian\Guardian;
use Illuminate\Support\Facades\Session;

class Logout
{
    public function __invoke()
    {
        Guardian::auth()->logout();

        Session::invalidate();
        Session::regenerateToken();

        return app(Guardian::getLogoutResponse());
    }
}
