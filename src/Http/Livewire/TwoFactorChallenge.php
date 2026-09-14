<?php

namespace Datalogix\Guardian\Http\Livewire;

use Datalogix\Guardian\Actions\ConfirmTwoFactorChallenge as ConfirmTwoFactorChallengeAction;
use Datalogix\Guardian\Enums\TwoFactorMethod;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Response\Redirector;

class TwoFactorChallenge extends Page
{
    public ?TwoFactorMethod $method = null;

    public string $code = '';

    public bool $remember_device = false;

    public function mount(): void
    {
        if (! Guardian::hasPendingTwoFactorChallenge()) {
            Redirector::redirectToLogin();

            return;
        }

        $this->method = Guardian::getPendingTwoFactorChallengeMethod();
    }

    public function submit()
    {
        $data = $this->validate(ConfirmTwoFactorChallengeAction::rules());

        $result = app(ConfirmTwoFactorChallengeAction::class)($data);

        return Guardian::respondToAuthFlow($result, Guardian::getLoginFeature()->getResponse());
    }

    public function resend(): void
    {
        Guardian::resendPendingTwoFactorChallengeCode();
    }
}
