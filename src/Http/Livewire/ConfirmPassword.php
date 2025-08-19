<?php

namespace Datalogix\Guardian\Http\Livewire;

use Datalogix\Guardian\Actions\ConfirmPassword as ConfirmPasswordAction;

class ConfirmPassword extends Page
{
    public string $password = '';

    public function submit()
    {
        $data = $this->validate(ConfirmPasswordAction::rules());

        return app(ConfirmPasswordAction::class)($data);
    }
}
