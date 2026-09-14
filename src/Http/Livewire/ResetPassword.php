<?php

namespace Datalogix\Guardian\Http\Livewire;

use Datalogix\Guardian\Actions\ResetPassword as ResetPasswordAction;
use Datalogix\Guardian\Enums\IdentifierKey;
use Datalogix\Guardian\Guardian;
use Livewire\Attributes\Locked;

class ResetPassword extends Page
{
    #[Locked()]
    public IdentifierKey $identifierKey;

    #[Locked]
    public string $token = '';

    public string $login = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(?string $token = null, ?string $login = null)
    {
        $this->identifierKey = Guardian::getIdentifierKey();
        $this->token = $token ?? request()->string('token');
        $this->login = $login ?? request()->string('login');
    }

    public function submit()
    {
        $data = $this->validate(ResetPasswordAction::rules());

        $status = app(ResetPasswordAction::class)($data);

        return app(Guardian::getResetPasswordFeature()->getResponse(), ['status' => $status]);
    }
}
