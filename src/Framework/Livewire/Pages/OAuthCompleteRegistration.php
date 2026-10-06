<?php

namespace Datalogix\Guardian\Framework\Livewire\Pages;

use Datalogix\Guardian\Actions\CompleteOAuthRegistration as CompleteOAuthRegistrationAction;
use Datalogix\Guardian\Enums\IdentifierKey;
use Datalogix\Guardian\Guardian;
use Livewire\Attributes\Locked;

class OAuthCompleteRegistration extends Page
{
    protected string $pageName = 'oauth-complete-registration';

    #[Locked]
    public IdentifierKey $identifierKey;

    public string $login = '';

    public function mount()
    {
        $this->identifierKey = Guardian::getIdentifierKey();
    }

    public function submit()
    {
        $action = app(CompleteOAuthRegistrationAction::class);
        $data = $this->validate($action::rules());

        $result = $action($data);

        return Guardian::respondToAuthFlow($result, Guardian::getOAuthFeature()->getResponse());
    }
}
