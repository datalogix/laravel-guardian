<?php

namespace Datalogix\Guardian\Http\Livewire;

use Datalogix\Guardian\Actions\ForgotPassword as ForgotPasswordAction;

class ForgotPassword extends Page
{
    public string $email = '';

    public function submit()
    {
        $data = $this->validate(ForgotPasswordAction::rules());

        return app(ForgotPasswordAction::class)($data);
    }
}
