<?php

namespace Datalogix\Guardian\Framework\Livewire\Pages;

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

    public string $login = '';

    public string $password = '';

    public string $password_confirmation = '';

    public bool $terms = false;

    public function mount()
    {
        $this->identifierKey = Guardian::getIdentifierKey();
    }

    public function submit()
    {
        return $this->forgettingSecrets(function () {
            // The rules of the action the application bound, which may ask for more fields.
            $action = app(SignUpAction::class);
            $data = $this->validate($action::rules());

            $result = $action($data);

            return Guardian::respondToAuthFlow($result, Guardian::getSignUpFeature()->getResponse());
        }, 'password', 'password_confirmation');
    }
}
