<?php

namespace Datalogix\Guardian\Framework\Livewire\Pages;

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
        return $this->forgettingSecrets(function () {
            $action = app(LoginAction::class);
            $data = $this->validate($action::rules());

            $result = $action($data, $this->remember);

            return Guardian::respondToAuthFlow($result, Guardian::getLoginFeature()->getResponse());
        }, 'password');
    }
}
