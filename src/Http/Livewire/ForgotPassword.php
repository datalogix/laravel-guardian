<?php

namespace Datalogix\Guardian\Http\Livewire;

use Datalogix\Guardian\Actions\ForgotPassword as ForgotPasswordAction;
use Datalogix\Guardian\Guardian;

class ForgotPassword extends Page
{
    public string $email = '';

    public function submit()
    {
        $data = $this->validate(ForgotPasswordAction::rules());

        $status = app(ForgotPasswordAction::class)($data);

        return app(Guardian::getForgotPasswordFeature()->getResponse(), ['status' => $status]);
    }
}
