<?php

namespace Datalogix\Guardian\Http\Livewire;

use Datalogix\Guardian\Actions\Login as LoginAction;
use Datalogix\Guardian\Enums\IdentifierKey;
use Datalogix\Guardian\Guardian;
use Livewire\Attributes\Locked;

class Login extends Page
{
    #[Locked]
    public IdentifierKey $identifierKey;

    public string $login = '';

    public string $password = '';

    public bool $remember = true;

    public function mount()
    {
        $this->identifierKey = Guardian::getIdentifierKey();
    }

    public function submit()
    {
        $data = $this->validate(LoginAction::rules());

        return app(LoginAction::class)($data, $this->remember);
    }
}
