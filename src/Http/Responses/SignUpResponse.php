<?php

namespace Datalogix\Guardian\Http\Responses;

use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Http\Responses\Concerns\RedirectsToTwoFactorSetup;
use Datalogix\Guardian\Response\Redirector;
use Illuminate\Contracts\Support\Responsable;

class SignUpResponse implements Responsable
{
    use RedirectsToTwoFactorSetup;

    public function toResponse($request)
    {
        if ($this->shouldRedirectToTwoFactorSetup()) {
            return Redirector::redirect(Guardian::getTwoFactorSetupFeature()->getUrl(), false);
        }

        return $this->redirectForRequiredEmailVerification() ?? Redirector::redirectIntended();
    }
}
