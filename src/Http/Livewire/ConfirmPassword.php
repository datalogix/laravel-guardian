<?php

namespace Datalogix\Guardian\Http\Livewire;

use Datalogix\Guardian\Actions\ConfirmPassword as ConfirmPasswordAction;
use Datalogix\Guardian\Guardian;

class ConfirmPassword extends Page
{
    public string $password = '';

    public function submit()
    {
        $data = $this->validate(ConfirmPasswordAction::rules());

        app(ConfirmPasswordAction::class)($data);

        return app(Guardian::getPasswordConfirmationFeature()->getResponse());
    }
}
