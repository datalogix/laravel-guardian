<?php

namespace Datalogix\Guardian\Framework\Livewire\Pages;

use Datalogix\Guardian\Actions\ConfirmPassword as ConfirmPasswordAction;
use Datalogix\Guardian\Guardian;

class ConfirmPassword extends Page
{
    public string $password = '';

    public function submit()
    {
        return $this->forgettingSecrets(function () {
            $action = app(ConfirmPasswordAction::class);
            $data = $this->validate($action::rules());

            $action($data);

            return app(Guardian::getPasswordConfirmationFeature()->getResponse());
        }, 'password');
    }
}
