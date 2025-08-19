<?php

namespace Datalogix\Guardian\Http\Livewire;

use Datalogix\Guardian\Actions\ResetPassword as ResetPasswordAction;
use Livewire\Attributes\Locked;

class ResetPassword extends Page
{
    #[Locked]
    public string $token = '';

    public string $email = '';

    public string $new_password = '';

    public string $new_password_confirmation = '';

    public function mount(?string $token = null, ?string $email = null)
    {
        $this->token = $token ?? request()->string('token');
        $this->email = $email ?? request()->string('email');
    }

    public function submit()
    {
        $data = $this->validate(ResetPasswordAction::rules());

        return app(ResetPasswordAction::class)($data);
    }
}
