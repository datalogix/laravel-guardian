<?php

namespace Datalogix\Fortress\Actions\Auth;

use Datalogix\Fortress\Facades\Fortress;
use Datalogix\Fortress\Http\Responses\Auth\Contracts\LogoutResponse;

class Logout
{
    public function __invoke(): ?LogoutResponse
    {
        Fortress::auth()->logout();

        session()->invalidate();
        session()->regenerateToken();

        return app(LogoutResponse::class);
    }
}
