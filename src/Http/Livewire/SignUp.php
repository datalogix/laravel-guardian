<?php

namespace Datalogix\Guardian\Http\Livewire;

use Datalogix\Guardian\Actions\SignUp as SignUpAction;
use Datalogix\Guardian\Enums\IdentifierKey;
use Datalogix\Guardian\Guardian;
use Livewire\Attributes\Locked;

class SignUp extends Page
{
    #[Locked]
    public IdentifierKey $identifierKey;

    public string $name = '';

    public string $email = '';

    public string $username = '';

    public string $password = '';

    public string $password_confirmation = '';

    public bool $terms = false;

    public function mount()
    {
        $this->identifierKey = Guardian::getIdentifierKey();
    }

    public function submit()
    {
        $data = $this->validate(SignUpAction::rules());

        return app(SignUpAction::class)($data);
    }
}
