<?php

namespace Datalogix\Guardian\Framework\Livewire\Pages;

use Datalogix\Guardian\Actions\ConfirmTwoFactorChallenge as ConfirmTwoFactorChallengeAction;
use Datalogix\Guardian\Enums\TwoFactorMethod;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Response\Redirector;
use Livewire\Attributes\Locked;

class TwoFactorChallenge extends Page
{
    #[Locked]
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
        return $this->forgettingSecrets(function () {
            $action = app(ConfirmTwoFactorChallengeAction::class);
            $data = $this->validate($action::rules());

            $result = $action($data);

            return Guardian::respondToAuthFlow($result, Guardian::getLoginFeature()->getResponse());
        }, 'code');
    }

    public function resend(): void
    {
        Guardian::resendPendingTwoFactorChallengeCode();
    }
}
