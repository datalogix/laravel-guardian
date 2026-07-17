<?php

namespace Datalogix\Guardian\Actions;

use Datalogix\Guardian\Guardian;
use Illuminate\Support\Facades\Session;

class Logout
{
    public function __invoke()
    {
        Guardian::forgetRememberedTwoFactorDevice();
        Guardian::auth()->logout();
        Guardian::clearTwoFactorSetup();
        Guardian::clearTwoFactorChallenge();
        Guardian::clearPendingTwoFactorSetup();

        Session::invalidate();
        Session::regenerateToken();
    }
}
