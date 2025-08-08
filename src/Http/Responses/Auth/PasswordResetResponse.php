<?php

namespace Datalogix\Fortress\Http\Responses\Auth;

use Datalogix\Fortress\Facades\Fortress;
use Datalogix\Fortress\Http\Responses\Auth\Contracts\PasswordResetResponse as Responsable;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

class PasswordResetResponse implements Responsable
{
    public function toResponse($request): RedirectResponse|Redirector|null
    {
        return Fortress::redirect(Fortress::hasLogin() ? Fortress::getLoginUrl() : null);
    }
}
