<?php

namespace Datalogix\Guardian\Http\Responses;

use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Response\Redirector;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Database\Eloquent\Model;

class LoginResponse implements Responsable
{
    public function toResponse($request)
    {
        if ($this->shouldRedirectToTwoFactorSetup()) {
            return Redirector::redirect(Guardian::getTwoFactorSetupFeature()->getUrl(), false);
        }

        return Redirector::redirectIntended();
    }

    protected function shouldRedirectToTwoFactorSetup(): bool
    {
        if (Guardian::hasPendingTwoFactorSetup()) {
            return true;
        }

        if (! Guardian::getTwoFactorSetupFeature()->hasFeature()) {
            return false;
        }

        $user = Guardian::user();

        if (! $user instanceof Model) {
            return false;
        }

        return Guardian::requiresTwoFactorSetup($user);
    }
}
