<?php

namespace Datalogix\Guardian\Actions\Concerns;

use Illuminate\Support\Facades\Session;

trait HasRecentPasswordConfirmation
{
    protected function passwordWasRecentlyConfirmed(): bool
    {
        $confirmedAt = Session::get('auth.password_confirmed_at');

        if (! is_numeric($confirmedAt)) {
            return false;
        }

        $timeout = (int) config('auth.password_timeout', 10800);

        return ((int) $confirmedAt + $timeout) > time();
    }
}
