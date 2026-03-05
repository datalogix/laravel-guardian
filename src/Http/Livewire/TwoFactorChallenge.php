<?php

namespace Datalogix\Guardian\Http\Livewire;

use Datalogix\Guardian\Actions\ConfirmTwoFactorChallenge as ConfirmTwoFactorChallengeAction;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Response\Redirector;

class TwoFactorChallenge extends Page
{
    public string $code = '';

    public bool $remember_device = false;

    public function mount(): void
    {
        if (! Guardian::hasPendingTwoFactorChallenge()) {
            Redirector::redirectToLogin();
        }
    }

    public function submit()
    {
        $data = $this->validate(ConfirmTwoFactorChallengeAction::rules());

        app(ConfirmTwoFactorChallengeAction::class)($data);

        return app(Guardian::getLoginFeature()->getResponse());
    }
}
